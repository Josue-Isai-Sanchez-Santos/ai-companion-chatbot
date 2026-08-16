<?php

use App\Enums\RelationshipStage;

return [
    /*
    |--------------------------------------------------------------------------
    | Initial relationship
    |--------------------------------------------------------------------------
    */

    'default_stage' => RelationshipStage::Strangers->value,

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
];
