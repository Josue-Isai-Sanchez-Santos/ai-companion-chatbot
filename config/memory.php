<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Memory system
    |--------------------------------------------------------------------------
    */

    'enabled' => env(
        'MEMORY_ENABLED',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | Semantic retrieval
    |--------------------------------------------------------------------------
    */

    'retrieval_limit' => (int) env(
        'MEMORY_RETRIEVAL_LIMIT',
        8
    ),

    'minimum_similarity' => (float) env(
        'MEMORY_MINIMUM_SIMILARITY',
        0.64
    ),

    'minimum_importance' => (float) env(
        'MEMORY_MINIMUM_IMPORTANCE',
        0.40
    ),

    /*
    |--------------------------------------------------------------------------
    | Automatic memory extraction
    |--------------------------------------------------------------------------
    */

    'extraction' => [
        'enabled' => env(
            'MEMORY_EXTRACTION_ENABLED',
            true
        ),

        'queue' => env(
            'MEMORY_EXTRACTION_QUEUE',
            'memory'
        ),

        'message_limit' => (int) env(
            'MEMORY_EXTRACTION_MESSAGE_LIMIT',
            8
        ),

        'max_memories_per_job' => (int) env(
            'MEMORY_EXTRACTION_MAX_MEMORIES',
            4
        ),

        'minimum_importance' => (float) env(
            'MEMORY_EXTRACTION_MINIMUM_IMPORTANCE',
            0.45
        ),

        'minimum_confidence' => (float) env(
            'MEMORY_EXTRACTION_MINIMUM_CONFIDENCE',
            0.70
        ),

        'duplicate_similarity' => (float) env(
            'MEMORY_EXTRACTION_DUPLICATE_SIMILARITY',
            0.92
        ),

        'provider' => env(
            'MEMORY_EXTRACTION_PROVIDER'
        ),

        'model' => env(
            'MEMORY_EXTRACTION_MODEL'
        ),

        'timeout' => (int) env(
            'MEMORY_EXTRACTION_TIMEOUT',
            120
        ),
    ],
];
