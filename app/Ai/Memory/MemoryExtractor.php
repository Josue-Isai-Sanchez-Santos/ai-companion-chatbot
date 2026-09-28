<?php

namespace App\Ai\Memory;

use App\Actions\Memories\CreateMemoryAction;
use App\Ai\Contracts\EmbeddingGateway;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Memory;
use App\Models\Message;
use App\Models\User;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;

final class MemoryExtractor
{
    public function __construct(
        private readonly MemoryExtractionAgent $agent,
        private readonly EmbeddingGateway $embeddingGateway,
        private readonly CreateMemoryAction $createMemoryAction,
    ) {}

    /**
     * @return list<Memory>
     */
    public function extract(
        Conversation $conversation,
        int $throughMessageId
    ): array {
        if (
            ! (bool) config(
                'memory.extraction.enabled',
                true
            )
        ) {
            return [];
        }

        $anchor = $conversation
            ->messages()
            ->whereKey(
                $throughMessageId
            )
            ->where(
                'role',
                MessageRole::Assistant->value
            )
            ->where(
                'status',
                Message::STATUS_COMPLETED
            )
            ->first();

        if ($anchor === null) {
            return [];
        }

        $profile = $conversation
            ->userCharacterProfile()
            ->with('user')
            ->firstOrFail();

        $user = $profile->user;

        $messageLimit = max(
            2,
            (int) config(
                'memory.extraction.message_limit',
                8
            )
        );

        $messages = $conversation
            ->messages()
            ->where(
                'id',
                '<=',
                $throughMessageId
            )
            ->where(
                'status',
                Message::STATUS_COMPLETED
            )
            ->orderByDesc(
                'created_at'
            )
            ->orderByDesc(
                'id'
            )
            ->limit(
                $messageLimit
            )
            ->get()
            ->reverse()
            ->values();

        if ($messages->count() < 2) {
            return [];
        }

        $response = $this
            ->agent
            ->prompt(
                $this->buildPrompt(
                    $messages
                ),
                provider:
                    $this->provider(),
                model:
                    $this->model(),
                timeout:
                    max(
                        1,
                        (int) config(
                            'memory.extraction.timeout',
                            120
                        )
                    )
            );

        if (
            ! $response
                instanceof StructuredAgentResponse
        ) {
            throw new LogicException(
                'Memory extraction agent did not return structured output.'
            );
        }

        $rawMemories =
            $response['memories']
            ?? [];

        if (! is_array($rawMemories)) {
            return [];
        }

        $minimumImportance =
            max(
                0.0,
                min(
                    1.0,
                    (float) config(
                        'memory.extraction.minimum_importance',
                        0.45
                    )
                )
            );

        $minimumConfidence =
            max(
                0.0,
                min(
                    1.0,
                    (float) config(
                        'memory.extraction.minimum_confidence',
                        0.70
                    )
                )
            );

        $maxMemories = max(
            0,
            (int) config(
                'memory.extraction.max_memories_per_job',
                4
            )
        );

        if ($maxMemories === 0) {
            return [];
        }

        $allowedMessageIds =
            array_fill_keys(
                $messages
                    ->pluck('id')
                    ->map(
                        static fn (
                            mixed $id
                        ): int =>
                            (int) $id
                    )
                    ->all(),
                true
            );

        $candidates = [];
        $seen = [];

        foreach (
            array_slice(
                $rawMemories,
                0,
                12
            ) as $rawMemory
        ) {
            if (! is_array($rawMemory)) {
                continue;
            }

            $candidate =
                ExtractedMemory::fromArray(
                    $rawMemory
                );

            if ($candidate === null) {
                continue;
            }

            if (
                $candidate->importance
                    < $minimumImportance
                || $candidate->confidence
                    < $minimumConfidence
            ) {
                continue;
            }

            $fingerprint =
                $this->fingerprint(
                    $candidate->content
                );

            if (
                isset(
                    $seen[
                        $fingerprint
                    ]
                )
            ) {
                continue;
            }

            $seen[$fingerprint] =
                true;

            $candidates[] =
                $candidate;
        }

        $created = [];

        foreach ($candidates as $candidate) {
            if (
                count($created)
                >= $maxMemories
            ) {
                break;
            }

            $embedding =
                $this
                    ->embeddingGateway
                    ->embed(
                        $candidate->content
                    );

            if (
                count($embedding)
                !== Memory::EMBEDDING_DIMENSIONS
            ) {
                throw new LogicException(
                    'Memory extraction embedding has an invalid number of dimensions.'
                );
            }

            $sourceMessageId =
                $candidate
                    ->sourceMessageId;

            if (
                $sourceMessageId !== null
                && ! isset(
                    $allowedMessageIds[
                        $sourceMessageId
                    ]
                )
            ) {
                $sourceMessageId = null;
            }

            $memory = $this
                ->persistIfNovel(
                    $user,
                    $profile,
                    $candidate,
                    $embedding,
                    $sourceMessageId
                );

            if ($memory !== null) {
                $created[] =
                    $memory;
            }
        }

        return $created;
    }

    /**
     * @param  Collection<int, Message>  $messages
     */
    private function buildPrompt(
        Collection $messages
    ): string {
        $lines = [];

        foreach ($messages as $message) {
            $content = Str::limit(
                trim(
                    $message->content
                ),
                2000,
                '…'
            );

            $lines[] = sprintf(
                '[message_id=%d][role=%s] %s',
                $message->id,
                $message->role->value,
                $content
            );
        }

        return implode(
            "\n",
            [
                'Analyze the transcript below and extract only memories worthy of long-term conversational continuity.',
                '',
                '--- TRANSCRIPT START ---',
                ...$lines,
                '--- TRANSCRIPT END ---',
            ]
        );
    }

    private function provider(): string
    {
        $provider = trim(
            (string) config(
                'memory.extraction.provider'
            )
        );

        if ($provider !== '') {
            return $provider;
        }

        return (string) config(
            'ai.chat.provider'
        );
    }

    private function model(): string
    {
        $model = trim(
            (string) config(
                'memory.extraction.model'
            )
        );

        if ($model !== '') {
            return $model;
        }

        return (string) config(
            'ai.chat.model'
        );
    }

    /**
     * @param  list<float>  $embedding
     */
    private function persistIfNovel(
        User $user,
        UserCharacterProfile $profile,
        ExtractedMemory $candidate,
        array $embedding,
        ?int $sourceMessageId
    ): ?Memory {
        return DB::transaction(
            function () use (
                $user,
                $profile,
                $candidate,
                $embedding,
                $sourceMessageId
            ): ?Memory {
                $lockedProfile =
                    UserCharacterProfile::query()
                        ->whereKey(
                            $profile->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $fingerprint =
                    $this->fingerprint(
                        $candidate->content
                    );

                $existingContents =
                    $lockedProfile
                        ->memories()
                        ->available()
                        ->pluck(
                            'content'
                        );

                foreach (
                    $existingContents
                    as $existingContent
                ) {
                    if (
                        $this->fingerprint(
                            $existingContent
                        )
                        === $fingerprint
                    ) {
                        return null;
                    }
                }

                $duplicateSimilarity =
                    max(
                        0.0,
                        min(
                            1.0,
                            (float) config(
                                'memory.extraction.duplicate_similarity',
                                0.92
                            )
                        )
                    );

                $semanticDuplicate =
                    $lockedProfile
                        ->memories()
                        ->available()
                        ->whereNotNull(
                            'embedding'
                        )
                        ->whereVectorSimilarTo(
                            'embedding',
                            $embedding,
                            minSimilarity:
                                $duplicateSimilarity
                        )
                        ->exists();

                if ($semanticDuplicate) {
                    return null;
                }

                return $this
                    ->createMemoryAction
                    ->execute(
                        $user,
                        $lockedProfile,
                        [
                            'source_message_id' =>
                                $sourceMessageId,

                            'type' =>
                                $candidate->type,

                            'content' =>
                                $candidate->content,

                            'importance' =>
                                $candidate->importance,

                            'confidence' =>
                                $candidate->confidence,

                            'embedding' =>
                                $embedding,
                        ]
                    );
            }
        );
    }

    private function fingerprint(
        string $content
    ): string {
        $content = mb_strtolower(
            trim($content)
        );

        $content = preg_replace(
            '/[^\p{L}\p{N}]+/u',
            ' ',
            $content
        ) ?? $content;

        $content = preg_replace(
            '/\s+/u',
            ' ',
            $content
        ) ?? $content;

        return trim(
            $content
        );
    }
}
