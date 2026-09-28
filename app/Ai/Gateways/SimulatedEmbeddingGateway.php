<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\EmbeddingGateway;
use App\Models\Memory;

final class SimulatedEmbeddingGateway implements EmbeddingGateway
{
    public function embed(
        string $text
    ): array {
        $dimensions =
            Memory::EMBEDDING_DIMENSIONS;

        $vector = array_fill(
            0,
            $dimensions,
            0.0
        );

        $normalized = mb_strtolower(
            trim($text)
        );

        $tokens = preg_split(
            '/[^\p{L}\p{N}]+/u',
            $normalized,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (
            $tokens === false
            || $tokens === []
        ) {
            $vector[0] = 1.0;

            return $vector;
        }

        foreach ($tokens as $token) {
            $hash = hash(
                'sha256',
                $token
            );

            $index = (int) (
                hexdec(
                    substr(
                        $hash,
                        0,
                        8
                    )
                )
                % $dimensions
            );

            $vector[$index] += 1.0;
        }

        $magnitude = sqrt(
            array_sum(
                array_map(
                    static fn (
                        float $value
                    ): float =>
                        $value * $value,

                    $vector
                )
            )
        );

        if ($magnitude <= 0.0) {
            $vector[0] = 1.0;

            return $vector;
        }

        return array_map(
            static fn (
                float $value
            ): float =>
                $value / $magnitude,

            $vector
        );
    }
}
