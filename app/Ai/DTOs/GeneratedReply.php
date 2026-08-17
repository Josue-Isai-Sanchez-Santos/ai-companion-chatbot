<?php

namespace App\Ai\DTOs;

final readonly class GeneratedReply
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public array $metadata = [],
        public ?int $tokenCount = null,
        public string $status = 'completed',
    ) {}
}
