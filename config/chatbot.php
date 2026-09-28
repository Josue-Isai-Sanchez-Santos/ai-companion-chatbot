<?php

use App\Enums\CharacterMood;

return [
    /*
    |--------------------------------------------------------------------------
    | Conversation context
    |--------------------------------------------------------------------------
    */

    'recent_message_limit' => (int) env(
        'CHAT_RECENT_MESSAGE_LIMIT',
        20
    ),

    'message_max_length' => (int) env(
        'CHAT_MESSAGE_MAX_LENGTH',
        4000
    ),

    /*
    |--------------------------------------------------------------------------
    | Conversation summaries
    |--------------------------------------------------------------------------
    */

    'summary' => [
        'enabled' => env(
            'CONVERSATION_SUMMARY_ENABLED',
            true
        ),

        'queue' => env(
            'CONVERSATION_SUMMARY_QUEUE',
            'summary'
        ),

        'message_threshold' => (int) env(
            'CONVERSATION_SUMMARY_MESSAGE_THRESHOLD',
            12
        ),

        'recent_message_limit' => (int) env(
            'CONVERSATION_SUMMARY_RECENT_MESSAGE_LIMIT',
            8
        ),

        'max_messages_per_refresh' => (int) env(
            'CONVERSATION_SUMMARY_MAX_MESSAGES',
            40
        ),

        'max_characters' => (int) env(
            'CONVERSATION_SUMMARY_MAX_CHARACTERS',
            2500
        ),

        'provider' => env(
            'CONVERSATION_SUMMARY_PROVIDER'
        ),

        'model' => env(
            'CONVERSATION_SUMMARY_MODEL'
        ),

        'timeout' => (int) env(
            'CONVERSATION_SUMMARY_TIMEOUT',
            120
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Character defaults
    |--------------------------------------------------------------------------
    */

    'default_mood' => CharacterMood::Neutral->value,

    /*
    |--------------------------------------------------------------------------
    | Reset
    |--------------------------------------------------------------------------
    */

    'reset_confirmation' => env(
        'CHAT_RESET_CONFIRMATION',
        'BORRAR'
    ),

    /*
    |--------------------------------------------------------------------------
    | Streaming
    |--------------------------------------------------------------------------
    */

    'streaming' => env(
        'CHAT_STREAMING',
        true
    ),
];
