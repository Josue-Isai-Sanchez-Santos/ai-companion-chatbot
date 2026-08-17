<?php

use App\Ai\Gateways\SimulatedChatGateway;
use App\Ai\Gateways\SimulatedEmbeddingGateway;

return [
    'chat' => [
        'driver' => env(
            'AI_CHAT_DRIVER',
            'simulated'
        ),

        'drivers' => [
            'simulated' => SimulatedChatGateway::class,
        ],
    ],

    'embedding' => [
        'driver' => env(
            'AI_EMBEDDING_DRIVER',
            'simulated'
        ),

        'drivers' => [
            'simulated' => SimulatedEmbeddingGateway::class,
        ],
    ],
];
