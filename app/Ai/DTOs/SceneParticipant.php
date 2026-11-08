<?php

namespace App\Ai\DTOs;

use InvalidArgumentException;
use JsonSerializable;

final readonly class SceneParticipant implements JsonSerializable
{
    public function __construct(
        public int $profileId,
        public string $name,
        public bool $present,
    ) {
        if (
            $profileId < 1
            || trim($name) === ''
            || mb_strlen($name) > 120
        ) {
            throw new InvalidArgumentException(
                'Invalid scene participant.'
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'profile_id' => $this->profileId,
            'name' => $this->name,
            'present' => $this->present,
        ];
    }
}
