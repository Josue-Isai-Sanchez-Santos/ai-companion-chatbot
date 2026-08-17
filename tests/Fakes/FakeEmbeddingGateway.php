<?php

namespace Tests\Fakes;

use App\Ai\Contracts\EmbeddingGateway;

final class FakeEmbeddingGateway implements EmbeddingGateway
{
    /**
     * @var list<string>
     */
    public array $inputs = [];

    /**
     * @var list<float>
     */
    private array $embedding = [
        0.1,
        0.2,
        0.3,
    ];

    /**
     * @param  list<float>  $embedding
     */
    public function returnEmbedding(
        array $embedding
    ): void {
        $this->embedding = $embedding;
    }

    public function embed(
        string $text
    ): array {
        $this->inputs[] = $text;

        return $this->embedding;
    }
}
