<?php

use App\Enums\RelationshipStage;

return [
    /*
    |--------------------------------------------------------------------------
    | Initial relationship
    |--------------------------------------------------------------------------
    */

    'default_stage' =>
        RelationshipStage::Strangers->value,

    /*
    |--------------------------------------------------------------------------
    | Relationship metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trust' => [
            'min' => 0,
            'max' => 100,
            'default' => 0,
        ],

        'affection' => [
            'min' => 0,
            'max' => 100,
            'default' => 0,
        ],

        'familiarity' => [
            'min' => 0,
            'max' => 100,
            'default' => 0,
        ],

        'tension' => [
            'min' => 0,
            'max' => 100,
            'default' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic relationship analysis
    |--------------------------------------------------------------------------
    */

    'analysis' => [
        'enabled' => env(
            'RELATIONSHIP_ANALYSIS_ENABLED',
            true
        ),

        'queue' => env(
            'RELATIONSHIP_ANALYSIS_QUEUE',
            'relationship'
        ),

        'provider' => env(
            'RELATIONSHIP_ANALYSIS_PROVIDER'
        ),

        'model' => env(
            'RELATIONSHIP_ANALYSIS_MODEL'
        ),

        'timeout' => (int) env(
            'RELATIONSHIP_ANALYSIS_TIMEOUT',
            120
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum movement caused by one significant event
    |--------------------------------------------------------------------------
    |
    | The AI may propose a larger change, but the backend
    | will never apply more than these values in one event.
    |
    */

    'max_delta_per_event' => [
        'trust' => 3,
        'affection' => 3,
        'familiarity' => 3,
        'tension' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationship stage thresholds
    |--------------------------------------------------------------------------
    |
    | Stage changes are calculated by the backend.
    | The AI never chooses a relationship stage.
    |
    */

    'stage_thresholds' => [
        RelationshipStage::Strangers->value => [
            'trust' => 0,
            'affection' => 0,
            'familiarity' => 0,
            'max_tension' => 100,
        ],

        RelationshipStage::Acquaintances->value => [
            'trust' => 0,
            'affection' => 0,
            'familiarity' => 10,
            'max_tension' => 100,
        ],

        RelationshipStage::Friends->value => [
            'trust' => 20,
            'affection' => 10,
            'familiarity' => 25,
            'max_tension' => 80,
        ],

        RelationshipStage::CloseFriends->value => [
            'trust' => 45,
            'affection' => 30,
            'familiarity' => 50,
            'max_tension' => 65,
        ],

        RelationshipStage::RomanticInterest->value => [
            'trust' => 60,
            'affection' => 60,
            'familiarity' => 65,
            'max_tension' => 50,
        ],

        RelationshipStage::Partners->value => [
            'trust' => 75,
            'affection' => 80,
            'familiarity' => 80,
            'max_tension' => 40,
        ],
    ],
];
