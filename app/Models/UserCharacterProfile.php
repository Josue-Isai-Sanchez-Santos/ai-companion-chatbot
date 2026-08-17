<?php

namespace App\Models;

use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCharacterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'character_id',
        'custom_personality',
        'custom_speaking_style',
        'custom_scenario',
        'nickname_for_user',
        'nickname_for_character',
        'current_mood',
        'current_expression_id',
        'relationship_stage',
        'trust',
        'affection',
        'familiarity',
        'tension',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_personality' => 'array',
            'custom_speaking_style' => 'array',
            'current_mood' => CharacterMood::class,
            'relationship_stage' => RelationshipStage::class,
            'trust' => 'integer',
            'affection' => 'integer',
            'familiarity' => 'integer',
            'tension' => 'integer',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function currentExpression(): BelongsTo
    {
        return $this->belongsTo(
            CharacterExpression::class,
            'current_expression_id'
        );
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
