<?php

namespace Tests\Unit\Enums;

use App\Enums\CharacterMood;
use App\Enums\MemoryType;
use App\Enums\MessageRole;
use App\Enums\RelationshipStage;
use App\Enums\ResetScope;
use PHPUnit\Framework\TestCase;

class DomainEnumsTest extends TestCase
{
    public function test_character_moods_have_expected_values(): void
    {
        $this->assertSame([
            'neutral',
            'happy',
            'sad',
            'angry',
            'embarrassed',
            'surprised',
            'curious',
        ], array_column(CharacterMood::cases(), 'value'));
    }

    public function test_memory_types_have_expected_values(): void
    {
        $this->assertSame([
            'user_fact',
            'user_preference',
            'character_fact',
            'shared_event',
            'promise',
            'relationship_event',
            'world_fact',
            'temporary_context',
        ], array_column(MemoryType::cases(), 'value'));
    }

    public function test_message_roles_have_expected_values(): void
    {
        $this->assertSame([
            'system',
            'user',
            'assistant',
        ], array_column(MessageRole::cases(), 'value'));
    }

    public function test_relationship_stages_have_expected_values(): void
    {
        $this->assertSame([
            'strangers',
            'acquaintances',
            'friends',
            'close_friends',
            'romantic_interest',
            'partners',
        ], array_column(RelationshipStage::cases(), 'value'));
    }

    public function test_reset_scope_has_only_full_character_reset(): void
    {
        $this->assertSame([
            'full_character',
        ], array_column(ResetScope::cases(), 'value'));
    }
}
