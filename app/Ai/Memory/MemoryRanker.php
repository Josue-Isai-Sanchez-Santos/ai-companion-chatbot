<?php

namespace App\Ai\Memory;

use App\Models\Memory;
use Illuminate\Support\Collection;

final class MemoryRanker
{
    private const SIMILARITY_WEIGHT = 0.70;

    private const IMPORTANCE_WEIGHT = 0.20;

    private const CONFIDENCE_WEIGHT = 0.10;

    /**
     * @param  Collection<int, Memory>  $memories
     * @param  list<float>  $queryEmbedding
     * @return Collection<int, Memory>
     */
    public function rank(
        Collection $memories,
        array $queryEmbedding
    ): Collection {
        return $memories
            ->map(
                function (
                    Memory $memory
                ) use (
                    $queryEmbedding
                ): Memory {
                    $similarity =
                        $this->cosineSimilarity(
                            $queryEmbedding,
                            $memory->embedding
                                ?? []
                        );

                    $importance = max(
                        0.0,
                        min(
                            1.0,
                            (float)
                                $memory->importance
                        )
                    );

                    $confidence = max(
                        0.0,
                        min(
                            1.0,
                            (float)
                                $memory->confidence
                        )
                    );

                    $score =
                        (
                            $similarity
                            * self::SIMILARITY_WEIGHT
                        )
                        + (
                            $importance
                            * self::IMPORTANCE_WEIGHT
                        )
                        + (
                            $confidence
                            * self::CONFIDENCE_WEIGHT
                        );

                    $memory->setAttribute(
                        'semantic_similarity',
                        $similarity
                    );

                    $memory->setAttribute(
                        'retrieval_score',
                        $score
                    );

                    return $memory;
                }
            )
            ->sortByDesc(
                static fn (
                    Memory $memory
                ): float =>
                    (float) $memory
                        ->getAttribute(
                            'retrieval_score'
                        )
            )
            ->values();
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    public function cosineSimilarity(
        array $left,
        array $right
    ): float {
        if (
            $left === []
            || count($left)
                !== count($right)
        ) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach (
            $left as $index => $leftValue
        ) {
            $leftValue =
                (float) $leftValue;

            $rightValue =
                (float) $right[$index];

            $dotProduct +=
                $leftValue
                * $rightValue;

            $leftMagnitude +=
                $leftValue
                * $leftValue;

            $rightMagnitude +=
                $rightValue
                * $rightValue;
        }

        if (
            $leftMagnitude <= 0.0
            || $rightMagnitude <= 0.0
        ) {
            return 0.0;
        }

        $similarity =
            $dotProduct
            / (
                sqrt($leftMagnitude)
                * sqrt($rightMagnitude)
            );

        return max(
            -1.0,
            min(
                1.0,
                $similarity
            )
        );
    }
}
