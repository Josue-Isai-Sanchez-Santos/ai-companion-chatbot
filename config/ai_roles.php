<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Companion v2.0
    |--------------------------------------------------------------------------
    |
    | Por defecto, la arquitectura v2 permanece
    | desactivada para conservar el comportamiento
    | original de v1.0.
    |
    */

    'enabled' => env(
        'AI_V2_ENABLED',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | Inference Mode
    |--------------------------------------------------------------------------
    |
    | Durante el desarrollo utilizaremos simuladores.
    | La autorización efectiva de solicitudes externas
    | se implementará en la capa de ejecución.
    |
    */

    'inference_mode' => env(
        'AI_INFERENCE_MODE',
        'simulated'
    ),

    'external_requests_allowed' => env(
        'AI_EXTERNAL_REQUESTS_ALLOWED',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | Scene Director
    |--------------------------------------------------------------------------
    |
    | Modelo encargado de planificar intervenciones,
    | seleccionar participantes y proponer decisiones.
    |
    */

    'director' => [

        'driver' => env(
            'AI_DIRECTOR_DRIVER',
            'simulated'
        ),

        'provider' => env(
            'AI_DIRECTOR_PROVIDER',
            null
        ),

        'model' => env(
            'AI_DIRECTOR_MODEL',
            null
        ),

        'timeout' => (int) env(
            'AI_DIRECTOR_TIMEOUT',
            60
        ),

        'context_window' => env(
            'AI_DIRECTOR_CONTEXT_WINDOW'
        ),

        'output_format' => env(
            'AI_DIRECTOR_OUTPUT_FORMAT',
            'json'
        ),

        'supports_streaming' => env(
            'AI_DIRECTOR_STREAMING',
            false
        ),

        'supports_structured_output' => env(
            'AI_DIRECTOR_STRUCTURED_OUTPUT',
            true
        ),

        'supports_cancellation' => env(
            'AI_DIRECTOR_CANCELLATION',
            false
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Narrative Generator
    |--------------------------------------------------------------------------
    |
    | Modelo encargado de generar las intervenciones
    | narrativas de cada personaje.
    |
    */

    'narrator' => [

        'driver' => env(
            'AI_NARRATOR_DRIVER',
            'simulated'
        ),

        'provider' => env(
            'AI_NARRATOR_PROVIDER',
            null
        ),

        'model' => env(
            'AI_NARRATOR_MODEL',
            null
        ),

        'timeout' => (int) env(
            'AI_NARRATOR_TIMEOUT',
            120
        ),

        'context_window' => env(
            'AI_NARRATOR_CONTEXT_WINDOW'
        ),

        'output_format' => env(
            'AI_NARRATOR_OUTPUT_FORMAT',
            'segments'
        ),

        'supports_streaming' => env(
            'AI_NARRATOR_STREAMING',
            true
        ),

        'supports_structured_output' => env(
            'AI_NARRATOR_STRUCTURED_OUTPUT',
            true
        ),

        'supports_cancellation' => env(
            'AI_NARRATOR_CANCELLATION',
            false
        ),

    ],

];
