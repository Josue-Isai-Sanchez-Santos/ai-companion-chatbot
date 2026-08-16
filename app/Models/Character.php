<?php

namespace App\Models;

use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_personality',
        'base_backstory',
        'base_speaking_style',
        'base_scenario',
        'system_rules',
        'initial_message',
        'avatar_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_personality' => 'array',
            'base_speaking_style' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
