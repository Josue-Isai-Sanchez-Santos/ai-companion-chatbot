<?php

namespace App\Ai\Memory;

use App\Actions\Memories\CreateMemoryAction;
use App\Ai\Contracts\EmbeddingGateway;
use App\Enums\MemoryType;
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

        $messagesById = $messages
            ->keyBy(
                static fn (
                    Message $message
                ): int =>
                    $message->id
            );

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

            $candidate =
                $this->normalizeCandidateType(
                    $candidate,
                    $messagesById
                );

            if (
                $candidate->importance
                    < $minimumImportance
                || $candidate->confidence
                    < $minimumConfidence
            ) {
                continue;
            }

            if (
                $this->shouldRejectCandidate(
                    $candidate,
                    $messagesById
                )
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

    /**
     * Reject obvious false-positive memories even when
     * the extraction model proposes them.
     *
     * @param  Collection<int, Message>  $messagesById
     */
    /**
     * Correct obvious type mistakes from a small
     * local extraction model.
     *
     * @param  Collection<int, Message>  $messagesById
     */
    private function normalizeCandidateType(
        ExtractedMemory $candidate,
        Collection $messagesById
    ): ExtractedMemory {
        if (
            $candidate->type
                !== MemoryType::UserFact
            || $candidate->sourceMessageId
                === null
        ) {
            return $candidate;
        }

        $sourceMessage = $messagesById
            ->get(
                $candidate->sourceMessageId
            );

        if (
            ! $sourceMessage
                instanceof Message
            || $sourceMessage->role
                !== MessageRole::User
        ) {
            return $candidate;
        }

        if (
            ! $this->containsPreferenceCue(
                $sourceMessage->content
            )
        ) {
            return $candidate;
        }

        return new ExtractedMemory(
            type:
                MemoryType::UserPreference,

            content:
                $candidate->content,

            importance:
                $candidate->importance,

            confidence:
                $candidate->confidence,

            sourceMessageId:
                $candidate->sourceMessageId,
        );
    }

    private function containsPreferenceCue(
        string $content
    ): bool {
        $content = mb_strtolower(
            $content
        );

        return preg_match(
            '/\b('
                .'favorito'
                .'|favorita'
                .'|favoritos'
                .'|favoritas'
                .'|prefiero'
                .'|preferir'
                .'|me gusta'
                .'|me gustan'
                .'|me encanta'
                .'|me encantan'
                .'|favorite'
                .'|favourite'
                .'|prefer'
                .'|like'
                .'|love'
            .')\b/u',
            $content
        ) === 1;
    }

    private function shouldRejectCandidate(
        ExtractedMemory $candidate,
        Collection $messagesById
    ): bool {
        $requiresUserSource = in_array(
            $candidate->type,
            [
                MemoryType::UserFact,
                MemoryType::UserPreference,
                MemoryType::SharedEvent,
            ],
            true
        );

        if (
            $candidate->sourceMessageId
            === null
        ) {
            return $requiresUserSource;
        }

        $sourceMessage = $messagesById
            ->get(
                $candidate->sourceMessageId
            );

        if (
            ! $sourceMessage
                instanceof Message
        ) {
            return $requiresUserSource;
        }

        /*
         * Facts/preferences about the user and
         * shared events must come from the user,
         * not from an assistant inference.
         */
        if (
            $requiresUserSource
            && $sourceMessage->role
                !== MessageRole::User
        ) {
            return true;
        }

        /*
         * Statements explicitly scoped to the
         * present moment are not long-term memory.
         */
        if (
            in_array(
                $candidate->type,
                [
                    MemoryType::UserFact,
                    MemoryType::UserPreference,
                ],
                true
            )
            && $this->containsTransientCue(
                $sourceMessage->content
            )
        ) {
            return true;
        }

        /*
         * A hypothetical suggestion is not an event
         * that has actually happened.
         */
        if (
            $candidate->type
                === MemoryType::SharedEvent
            && $this->containsHypotheticalCue(
                $sourceMessage->content
            )
        ) {
            return true;
        }

        /*
         * Ordinary character introductions already
         * exist in the character configuration.
         */
        if (
            $candidate->type
                === MemoryType::CharacterFact
            && $sourceMessage->role
                === MessageRole::Assistant
            && $this->looksLikeGenericIntroduction(
                $sourceMessage->content
            )
        ) {
            return true;
        }

        return false;
    }

    private function containsTransientCue(
        string $content
    ): bool {
        $content = mb_strtolower(
            $content
        );

        return preg_match(
            '/\b('
                .'hoy'
                .'|ahora'
                .'|ahorita'
                .'|por ahora'
                .'|en este momento'
                .'|esta mañana'
                .'|esta tarde'
                .'|esta noche'
                .'|momentáneamente'
                .'|temporalmente'
                .'|today'
                .'|right now'
                .'|currently'
                .'|for now'
                .'|at the moment'
                .'|this morning'
                .'|this afternoon'
                .'|tonight'
            .')\b/u',
            $content
        ) === 1;
    }

    private function containsHypotheticalCue(
        string $content
    ): bool {
        $content = mb_strtolower(
            $content
        );

        return preg_match(
            '/\b('
                .'quizás'
                .'|quiza'
                .'|quizá'
                .'|tal vez'
                .'|podríamos'
                .'|podriamos'
                .'|podría'
                .'|podria'
                .'|algún día'
                .'|algun dia'
                .'|maybe'
                .'|perhaps'
                .'|could'
                .'|might'
                .'|someday'
            .')\b/u',
            $content
        ) === 1;
    }

    private function looksLikeGenericIntroduction(
        string $content
    ): bool {
        $content = mb_strtolower(
            $content
        );

        return preg_match(
            '/('
                .'me llamo'
                .'|mi nombre es'
                .'|soy tu compañero'
                .'|soy tu compañera'
                .'|my name is'
                .'|i am your companion'
                ."|i'm your companion"
            .')/u',
            $content
        ) === 1;
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
