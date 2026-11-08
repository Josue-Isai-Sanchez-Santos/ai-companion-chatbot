<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class ProviderMetadata implements JsonSerializable
{
    public function __construct(
        public string $driver,
        public ?string $provider = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $latencyMs = null,
    ) {
        if (! in_array($driver, ['simulated', 'laravel'], true)) {
            throw new InvalidArgumentException(
                'Invalid provider driver.'
            );
        }

        if (
            $driver === 'simulated'
            && ($provider !== null || $model !== null)
        ) {
            throw new InvalidArgumentException(
                'Simulated metadata must not identify a real model.'
            );
        }

        if (
            $driver === 'laravel'
            && (
                $provider === null
                || ! preg_match('/^[a-z0-9_-]{1,60}$/D', $provider)
                || $model === null
                || ! preg_match('/^[A-Za-z0-9._\/:@+\-]{1,160}$/D', $model)
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid provider identifier or model identifier.'
            );
        }

        foreach ([$inputTokens, $outputTokens, $latencyMs] as $value) {
            if ($value !== null && $value < 0) {
                throw new InvalidArgumentException(
                    'Provider metrics cannot be negative.'
                );
            }
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'driver' => $this->driver,
            'simulated' => $this->driver === 'simulated',
            'provider' => $this->provider,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
