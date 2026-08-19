<?php

namespace App\Models;

use App\Enums\MemoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    public const EMBEDDING_DIMENSIONS = 1536;

    protected $fillable = [
        'user_character_profile_id',
        'source_message_id',
        'type',
        'content',
        'importance',
        'confidence',
        'embedding',
        'access_count',
        'last_accessed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MemoryType::class,

            'importance' => 'float',

            'confidence' => 'float',

            'embedding' => 'array',

            'access_count' => 'integer',

            'last_accessed_at' => 'datetime',

            'expires_at' => 'datetime',
        ];
    }

    public function userCharacterProfile(): BelongsTo
    {
        return $this->belongsTo(
            UserCharacterProfile::class
        );
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(
            Message::class,
            'source_message_id'
        );
    }

    public function scopeAvailable(
        Builder $query
    ): Builder {
        return $query->where(
            function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            }
        );
    }
}
