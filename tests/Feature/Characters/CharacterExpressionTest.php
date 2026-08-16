<?php

namespace Tests\Feature\Characters;

use App\Models\Character;
use Database\Seeders\CharacterSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterExpressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_has_many_expressions(): void
    {
        $character = Character::factory()->create();

        $character->expressions()->create([
            'name' => 'neutral',
            'description' => 'Neutral expression.',
            'is_default' => true,
        ]);

        $character->expressions()->create([
            'name' => 'happy',
            'description' => 'Happy expression.',
            'is_default' => false,
        ]);

        $this->assertCount(
            2,
            $character->expressions
        );
    }

    public function test_expression_belongs_to_character(): void
    {
        $character = Character::factory()->create();

        $expression = $character->expressions()->create([
            'name' => 'neutral',
            'description' => 'Neutral expression.',
            'is_default' => true,
        ]);

        $this->assertTrue(
            $expression->character->is($character)
        );
    }

    public function test_character_can_retrieve_default_expression(): void
    {
        $character = Character::factory()->create();

        $character->expressions()->create([
            'name' => 'neutral',
            'description' => 'Neutral expression.',
            'is_default' => true,
        ]);

        $character->expressions()->create([
            'name' => 'happy',
            'description' => 'Happy expression.',
            'is_default' => false,
        ]);

        $this->assertSame(
            'neutral',
            $character->defaultExpression?->name
        );
    }

    public function test_character_cannot_have_two_default_expressions(): void
    {
        $character = Character::factory()->create();

        $character->expressions()->create([
            'name' => 'neutral',
            'description' => 'Neutral expression.',
            'is_default' => true,
        ]);

        $this->expectException(QueryException::class);

        $character->expressions()->create([
            'name' => 'happy',
            'description' => 'Happy expression.',
            'is_default' => true,
        ]);
    }

    public function test_seeder_creates_base_expressions(): void
    {
        $this->seed(CharacterSeeder::class);

        $character = Character::query()
            ->where('slug', 'default-companion')
            ->with('expressions')
            ->firstOrFail();

        $this->assertSame(
            [
                'angry',
                'embarrassed',
                'happy',
                'neutral',
                'sad',
                'surprised',
            ],
            $character->expressions
                ->pluck('name')
                ->sort()
                ->values()
                ->all()
        );

        $this->assertCount(
            6,
            $character->expressions
        );

        $this->assertSame(
            1,
            $character->expressions
                ->where('is_default', true)
                ->count()
        );

        $this->assertSame(
            'neutral',
            $character->defaultExpression?->name
        );
    }

    public function test_expression_seeding_is_idempotent(): void
    {
        $this->seed(CharacterSeeder::class);
        $this->seed(CharacterSeeder::class);

        $character = Character::query()
            ->where('slug', 'default-companion')
            ->firstOrFail();

        $this->assertSame(
            6,
            $character->expressions()->count()
        );

        $this->assertSame(
            1,
            $character->expressions()
                ->where('is_default', true)
                ->count()
        );
    }
}
