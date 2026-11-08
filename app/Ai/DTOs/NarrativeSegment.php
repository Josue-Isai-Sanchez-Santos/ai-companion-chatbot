<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class NarrativeSegment implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $text,
    ) {
        if (
            ! in_array(
                $type,
                ['thought', 'action', 'dialogue'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid narrative segment type.'
            );
        }

        if (
            trim($text) === ''
            || mb_strlen($text) > 6000
        ) {
            throw new InvalidArgumentException(
                'Invalid narrative segment text.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
        ];
    }
}
