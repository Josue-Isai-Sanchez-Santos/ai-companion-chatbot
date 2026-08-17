<?php

namespace Tests\Fakes;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;

final class FakeChatGateway implements ChatGateway
{
    /**
     * @var list<ChatContext>
     */
    public array $contexts = [];

    private GeneratedReply $reply;

    public function __construct()
    {
        $this->reply = new GeneratedReply(
            content: 'Respuesta del FakeChatGateway.',
            metadata: [
                'fake' => true,
            ],
        );
    }

    public function replyWith(
        GeneratedReply $reply
    ): void {
        $this->reply = $reply;
    }

    public function generate(
        ChatContext $context
    ): GeneratedReply {
        $this->contexts[] = $context;

        return $this->reply;
    }
}
