<?php

namespace App\Ai\DTOs;

final readonly class CharacterContext
{
    /**
     * @param  array<string, mixed>  $personality
     * @param  array<string, mixed>  $speakingStyle
     * @param  array<string, mixed>  $customPersonality
     * @param  array<string, mixed>  $customSpeakingStyle
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

        public array $customPersonality = [],
        public array $customSpeakingStyle = [],
        public ?string $customScenario = null,

        public int $trust = 0,
        public int $affection = 0,
        public int $familiarity = 0,
        public int $tension = 0,
    ) {}
}
