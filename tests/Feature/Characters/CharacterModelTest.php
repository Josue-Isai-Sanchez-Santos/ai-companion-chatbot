<?php

namespace Tests\Feature\Characters;

use App\Models\Character;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_can_be_created_with_factory(): void
    {
        $character = Character::factory()->create();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'slug' => $character->slug,
            'is_active' => true,
        ]);
    }

    public function test_character_json_fields_are_cast_to_arrays(): void
    {
        $character = Character::factory()->create();

        $this->assertIsArray($character->base_personality);
        $this->assertIsArray($character->base_speaking_style);

        $this->assertArrayHasKey(
            'traits',
            $character->base_personality
        );

        $this->assertArrayHasKey(
            'language',
            $character->base_speaking_style
        );
    }

    public function test_active_scope_returns_only_active_characters(): void
    {
        $active = Character::factory()->create();

        Character::factory()
            ->inactive()
            ->create();

        $characters = Character::query()
            ->active()
            ->get();

        $this->assertCount(1, $characters);

        $this->assertTrue(
            $characters->first()->is($active)
        );
    }

    public function test_default_character_seeder_creates_character(): void
    {
        $this->seed(CharacterSeeder::class);

        $character = Character::query()
            ->where('slug', 'default-companion')
            ->first();

        $this->assertNotNull($character);
        $this->assertSame('Default Companion', $character->name);
        $this->assertTrue($character->is_active);
    }

    public function test_default_character_seeder_is_idempotent(): void
    {
        $this->seed(CharacterSeeder::class);
        $this->seed(CharacterSeeder::class);

        $this->assertSame(
            1,
            Character::query()
                ->where('slug', 'default-companion')
                ->count()
        );
    }
}
