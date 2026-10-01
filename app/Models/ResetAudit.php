<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResetAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'character_id',
        'previous_profile_id',
        'new_profile_id',
        'deleted_conversations',
        'deleted_messages',
        'deleted_memories',
        'deleted_relationship_events',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_profile_id' =>
                'integer',

            'new_profile_id' =>
                'integer',

            'deleted_conversations' =>
                'integer',

            'deleted_messages' =>
                'integer',

            'deleted_memories' =>
                'integer',

            'deleted_relationship_events' =>
                'integer',

            'reset_at' =>
                'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(
            Character::class
        );
    }
}
