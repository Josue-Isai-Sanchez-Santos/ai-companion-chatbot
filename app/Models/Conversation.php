<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'title',
        'summary',
        'summary_updated_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_updated_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function userCharacterProfile(): BelongsTo
    {
        return $this->belongsTo(
            UserCharacterProfile::class
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
