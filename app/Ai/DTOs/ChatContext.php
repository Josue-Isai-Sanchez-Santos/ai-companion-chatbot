<?php

namespace App\Ai\DTOs;

final readonly class ChatContext
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<string>  $relevantMemories
     */
    public function __construct(
        public int $conversationId,
        public CharacterContext $character,
        public array $messages,

        public string $systemPrompt = '',
        public ?string $conversationSummary = null,
        public array $relevantMemories = [],
    ) {}
}
