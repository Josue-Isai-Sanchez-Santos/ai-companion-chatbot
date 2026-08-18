<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;

interface ChatGateway
{
    public function generate(
        ChatContext $context
    ): GeneratedReply;

    /**
     * @param  callable(string): void  $onDelta
     */
    public function stream(
        ChatContext $context,
        callable $onDelta
    ): GeneratedReply;
}
