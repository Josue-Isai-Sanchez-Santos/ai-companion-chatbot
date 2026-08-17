<?php

namespace App\Ai\DTOs;

final readonly class CharacterContext
{
    /**
     * @param  array<string, mixed>  $personality
     * @param  array<string, mixed>  $speakingStyle
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public array $personality,
        public ?string $backstory,
        public array $speakingStyle,
        public ?string $scenario,
        public ?string $systemRules,
        public string $mood,
        public string $relationshipStage,
        public ?string $nicknameForUser,
        public ?string $nicknameForCharacter,
    ) {}
}
