<?php

namespace App\Ai\Memory;

use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Exceptions\AiGatewayException;
use App\Models\Memory;
use App\Models\UserCharacterProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class MemoryRetriever
{
    private const CANDIDATE_MULTIPLIER = 4;

    public function __construct(
        private readonly EmbeddingGateway $embeddingGateway,
        private readonly MemoryRanker $ranker,
        private readonly MemoryDeduplicator $deduplicator,
    ) {}

    /**
     * @return Collection<int, Memory>
     */
    public function retrieve(
        UserCharacterProfile $profile,
        string $message
    ): Collection {
        if (
            ! (bool) config(
                'memory.enabled',
                true
            )
        ) {
            return collect();
        }

        $message = trim(
            $message
        );

        if ($message === '') {
            return collect();
        }

        $limit = max(
            0,
            (int) config(
                'memory.retrieval_limit',
                8
            )
        );

        if ($limit === 0) {
            return collect();
        }

        $minimumSimilarity = max(
            0.0,
            min(
                1.0,
                (float) config(
                    'memory.minimum_similarity',
                    0.65
                )
            )
        );

        $minimumImportance = max(
            0.0,
            min(
                1.0,
                (float) config(
                    'memory.minimum_importance',
                    0.40
                )
            )
        );

        $eligibleQuery = Memory::query()
            ->where(
                'user_character_profile_id',
                $profile->id
            )
            ->available()
            ->whereNotNull(
                'embedding'
            )
            ->where(
                'importance',
                '>=',
                $minimumImportance
            );

        /*
         * Do not call the embedding provider if this
         * profile has no eligible stored embeddings.
         */
        if (
            ! (clone $eligibleQuery)
                ->exists()
        ) {
            return collect();
        }

        try {
            $queryEmbedding =
                $this->embeddingGateway
                    ->embed(
                        $message
                    );
        } catch (AiGatewayException $exception) {
            /*
             * Semantic memory is useful context,
             * but failure to retrieve it should not
             * prevent the character from replying.
             */
            report(
                $exception
            );

            return collect();
        }

        if (
            count($queryEmbedding)
            !== Memory::EMBEDDING_DIMENSIONS
        ) {
            throw new LogicException(
                'Embedding gateway returned a vector with an invalid number of dimensions.'
            );
        }

        $candidateLimit = max(
            $limit,
            $limit
                * self::CANDIDATE_MULTIPLIER
        );

        $candidates = $eligibleQuery
            ->whereVectorSimilarTo(
                'embedding',
                $queryEmbedding,
                minSimilarity:
                    $minimumSimilarity
            )
            ->limit(
                $candidateLimit
            )
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $ranked = $this
            ->ranker
            ->rank(
                $candidates,
                $queryEmbedding
            );

        $selected = new \Illuminate\Database\Eloquent\Collection(
            $this
                ->deduplicator
                ->deduplicate(
                    $ranked
                )
                ->take(
                    $limit
                )
                ->values()
                ->all()
        );

        $this->recordAccesses(
            $profile,
            $selected
        );

        return $selected;
    }

    /**
     * @param  Collection<int, Memory>  $memories
     */
    private function recordAccesses(
        UserCharacterProfile $profile,
        Collection $memories
    ): void {
        if ($memories->isEmpty()) {
            return;
        }

        $now = now();

        Memory::query()
            ->where(
                'user_character_profile_id',
                $profile->id
            )
            ->whereIn(
                'id',
                $memories->modelKeys()
            )
            ->update([
                'access_count' =>
                    DB::raw(
                        'access_count + 1'
                    ),

                'last_accessed_at' =>
                    $now,
            ]);

        foreach ($memories as $memory) {
            $memory->setAttribute(
                'access_count',
                $memory->access_count + 1
            );

            $memory->setAttribute(
                'last_accessed_at',
                $now
            );
        }
    }
}
