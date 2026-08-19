<?php

namespace App\Models;

use App\Enums\MessageRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public const STATUS_STREAMING = 'streaming';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_INTERRUPTED = 'interrupted';

    protected $fillable = [
        'parent_message_id',
        'role',
        'content',
        'metadata',
        'token_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'metadata' => 'array',
            'token_count' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(
            Message::class,
            'parent_message_id'
        );
    }

    public function childMessages(): HasMany
    {
        return $this->hasMany(
            Message::class,
            'parent_message_id'
        );
    }

    public function sourceMemories(): HasMany
    {
        return $this->hasMany(
            Memory::class,
            'source_message_id'
        );
    }

    public function scopeChronological(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
