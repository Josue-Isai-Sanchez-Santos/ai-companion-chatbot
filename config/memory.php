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
        0.65
    ),

    'minimum_importance' => (float) env(
        'MEMORY_MINIMUM_IMPORTANCE',
        0.40
    ),
];
