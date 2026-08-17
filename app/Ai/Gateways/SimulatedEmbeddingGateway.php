<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\EmbeddingGateway;

final class SimulatedEmbeddingGateway implements EmbeddingGateway
{
    public function embed(
        string $text
    ): array {
        return [
            0.0,
            0.0,
            0.0,
        ];
    }
}
