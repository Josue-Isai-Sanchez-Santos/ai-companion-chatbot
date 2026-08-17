<?php

namespace App\Ai\DTOs;

final readonly class ChatContext
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function __construct(
        public int $conversationId,
        public CharacterContext $character,
        public array $messages,
    ) {}
}
