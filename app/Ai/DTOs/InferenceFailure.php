<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class InferenceFailure implements JsonSerializable
{
    public const CODES = [
        'timeout',
        'invalid_output',
        'unavailable',
        'cancelled',
        'internal_error',
    ];

    public function __construct(
        public string $code,
        public bool $retryable,
    ) {
        if (! in_array($code, self::CODES, true)) {
            throw new InvalidArgumentException(
                'Invalid inference failure code.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'retryable' => $this->retryable,
        ];
    }
}
