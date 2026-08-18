<?php

use App\Ai\Gateways\LaravelAiGateway;
use App\Ai\Gateways\SimulatedChatGateway;
use App\Ai\Gateways\SimulatedEmbeddingGateway;

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel AI default provider
    |--------------------------------------------------------------------------
    */

    'default' => 'openai',

    /*
    |--------------------------------------------------------------------------
    | Application chat gateway
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'driver' => env(
            'AI_CHAT_DRIVER',
            'simulated'
        ),

        'provider' => env(
            'AI_CHAT_PROVIDER',
            'openai'
        ),

        'model' => env(
            'AI_CHAT_MODEL',
            'gpt-5.4-mini'
        ),

        'timeout' => (int) env(
            'AI_CHAT_TIMEOUT',
            30
        ),

        'drivers' => [
            'simulated' => SimulatedChatGateway::class,

            'laravel' => LaravelAiGateway::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application embedding gateway
    |--------------------------------------------------------------------------
    */

    'embedding' => [
        'driver' => env(
            'AI_EMBEDDING_DRIVER',
            'simulated'
        ),

        'drivers' => [
            'simulated' => SimulatedEmbeddingGateway::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel AI providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'openai' => [
            'driver' => 'openai',

            'key' => env(
                'OPENAI_API_KEY'
            ),

            'url' => env(
                'OPENAI_URL',
                'https://api.openai.com/v1'
            ),

            'store' => env(
                'OPENAI_STORE',
                false
            ),
        ],
    ],
];
