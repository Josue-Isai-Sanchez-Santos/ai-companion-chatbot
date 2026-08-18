<?php

namespace App\Ai\Gateways;

use App\Ai\Contracts\ChatGateway;
use App\Ai\DTOs\ChatContext;
use App\Ai\DTOs\GeneratedReply;

final class SimulatedChatGateway implements ChatGateway
{
    public function generate(
        ChatContext $context
    ): GeneratedReply {
        return $this->reply();
    }

    public function stream(
        ChatContext $context,
        callable $onDelta
    ): GeneratedReply {
        foreach ([
            'Respuesta simulada: ',
            'recibí tu mensaje ',
            'correctamente.',
        ] as $delta) {
            $onDelta($delta);
        }

        return $this->reply();
    }

    private function reply(): GeneratedReply
    {
        return new GeneratedReply(
            content: 'Respuesta simulada: recibí tu mensaje correctamente.',

            metadata: [
                'simulated' => true,
                'driver' => 'simulated',
            ],
        );
    }
}
