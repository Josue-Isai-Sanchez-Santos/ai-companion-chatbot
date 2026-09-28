<?php

namespace App\Models;

use App\Enums\RelationshipStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipEvent extends Model
{
    protected $fillable = [
        'user_character_profile_id',
        'conversation_id',
        'user_message_id',
        'assistant_message_id',
        'event_summary',

        'from_stage',
        'to_stage',

        'requested_trust_delta',
        'applied_trust_delta',
        'trust_before',
        'trust_after',

        'requested_affection_delta',
        'applied_affection_delta',
        'affection_before',
        'affection_after',

        'requested_familiarity_delta',
        'applied_familiarity_delta',
        'familiarity_before',
        'familiarity_after',

        'requested_tension_delta',
        'applied_tension_delta',
        'tension_before',
        'tension_after',
    ];

    protected function casts(): array
    {
        return [
            'from_stage' =>
                RelationshipStage::class,

            'to_stage' =>
                RelationshipStage::class,

            'requested_trust_delta' =>
                'integer',

            'applied_trust_delta' =>
                'integer',

            'trust_before' =>
                'integer',

            'trust_after' =>
                'integer',

            'requested_affection_delta' =>
                'integer',

            'applied_affection_delta' =>
                'integer',

            'affection_before' =>
                'integer',

            'affection_after' =>
                'integer',

            'requested_familiarity_delta' =>
                'integer',

            'applied_familiarity_delta' =>
                'integer',

            'familiarity_before' =>
                'integer',

            'familiarity_after' =>
                'integer',

            'requested_tension_delta' =>
                'integer',

            'applied_tension_delta' =>
                'integer',

            'tension_before' =>
                'integer',

            'tension_after' =>
                'integer',
        ];
    }

    public function userCharacterProfile(): BelongsTo
    {
        return $this->belongsTo(
            UserCharacterProfile::class
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class
        );
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(
            Message::class,
            'user_message_id'
        );
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(
            Message::class,
            'assistant_message_id'
        );
    }
}
