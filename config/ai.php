<?php

use App\Ai\Gateways\LaravelAiEmbeddingGateway;
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
            'simulated' =>
                SimulatedChatGateway::class,

            'laravel' =>
                LaravelAiGateway::class,
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

        'provider' => env(
            'AI_EMBEDDING_PROVIDER',
            'openai'
        ),

        'model' => env(
            'AI_EMBEDDING_MODEL',
            'text-embedding-3-small'
        ),

        'dimensions' => (int) env(
            'AI_EMBEDDING_DIMENSIONS',
            1536
        ),

        'timeout' => (int) env(
            'AI_EMBEDDING_TIMEOUT',
            30
        ),

        'drivers' => [
            'simulated' =>
                SimulatedEmbeddingGateway::class,

            'laravel' =>
                LaravelAiEmbeddingGateway::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel AI providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'ollama' => [
            'driver' => 'ollama',

            'key' => env(
                'OLLAMA_API_KEY',
                ''
            ),

            'url' => env(
                'OLLAMA_URL',
                'http://127.0.0.1:11434'
            ),
        ],

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
