<?php

namespace App\Ai\Contracts;

use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;

interface ChatGateway
{
    public function generate(
        ChatContext $context
    ): GeneratedReply;
}
