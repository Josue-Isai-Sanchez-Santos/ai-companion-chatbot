<?php

namespace App\Ai\Memory;

use App\Models\Memory;
use Illuminate\Support\Collection;

final class MemoryDeduplicator
{
    private const SEMANTIC_DUPLICATE_THRESHOLD =
        0.985;

    public function __construct(
        private readonly MemoryRanker $ranker
    ) {}

    /**
     * @param  Collection<int, Memory>  $memories
     * @return Collection<int, Memory>
     */
    public function deduplicate(
        Collection $memories
    ): Collection {
        $unique = collect();

        foreach ($memories as $memory) {
            $duplicate = $unique->contains(
                function (
                    Memory $existing
                ) use (
                    $memory
                ): bool {
                    if (
                        $this->fingerprint(
                            $existing->content
                        )
                        ===
                        $this->fingerprint(
                            $memory->content
                        )
                    ) {
                        return true;
                    }

                    $left =
                        $existing->embedding
                        ?? [];

                    $right =
                        $memory->embedding
                        ?? [];

                    if (
                        $left === []
                        || $right === []
                    ) {
                        return false;
                    }

                    return $this
                        ->ranker
                        ->cosineSimilarity(
                            $left,
                            $right
                        )
                        >= self::SEMANTIC_DUPLICATE_THRESHOLD;
                }
            );

            if (! $duplicate) {
                $unique->push(
                    $memory
                );
            }
        }

        return $unique->values();
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
