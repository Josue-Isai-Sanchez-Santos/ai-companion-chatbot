<?php

namespace App\Ai\Contracts;

interface EmbeddingGateway
{
    /**
     * @return list<float>
     */
    public function embed(
        string $text
    ): array;
}
