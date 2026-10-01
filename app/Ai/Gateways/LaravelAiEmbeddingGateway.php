<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\EmbeddingGateway;
use App\Ai\Exceptions\AiGatewayException;
use App\Ai\Exceptions\AiProviderException;
use App\Models\Memory;
use Laravel\Ai\Embeddings;
use Throwable;

final class LaravelAiEmbeddingGateway implements EmbeddingGateway
{
    public function embed(
        string $text
    ): array {
        $text = trim(
            $text
        );

        if ($text === '') {
            throw new AiGatewayException(
                'Embedding input cannot be empty.'
            );
        }

        $provider = trim(
            (string) config(
                'ai.embedding.provider'
            )
        );

        $model = trim(
            (string) config(
                'ai.embedding.model'
            )
        );

        $dimensions = (int) config(
            'ai.embedding.dimensions',
            Memory::EMBEDDING_DIMENSIONS
        );

        $timeout = max(
            1,
            (int) config(
                'ai.embedding.timeout',
                30
            )
        );

        if (
            $provider === ''
            || $model === ''
        ) {
            throw new AiGatewayException(
                'AI embedding provider configuration is incomplete.'
            );
        }

        if (
            $dimensions
            !== Memory::EMBEDDING_DIMENSIONS
        ) {
            throw new AiGatewayException(
                'Configured embedding dimensions do not match the memories vector column.'
            );
        }

        try {
            $response = Embeddings::for([
                $text,
            ])
                ->dimensions(
                    $dimensions
                )
                ->timeout(
                    $timeout
                )
                ->generate(
                    $provider,
                    $model
                );
        } catch (Throwable $exception) {
            throw new AiProviderException(
                'AI embedding request failed.',
                0
            );
        }

        $embedding =
            $response->embeddings[0]
            ?? null;

        if (
            ! is_array($embedding)
            || count($embedding)
                !== $dimensions
        ) {
            throw new AiProviderException(
                'AI embedding provider returned an invalid vector.'
            );
        }

        return array_map(
            static fn (
                mixed $value
            ): float =>
                (float) $value,

            $embedding
        );
    }
}
