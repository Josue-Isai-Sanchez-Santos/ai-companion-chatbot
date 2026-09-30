<?php

namespace Tests\Feature\Chat;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Enums\CharacterMood;
use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterStateDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_displays_current_mood_and_expression(): void
    {
        $this->seed(
            CharacterSeeder::class
        );

        $user =
            User::factory()
                ->create();

        $character =
            Character::query()
                ->where(
                    'slug',
                    'default-companion'
                )
                ->firstOrFail();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $character
        );

        $happy =
            $character
                ->expressions()
                ->where(
                    'name',
                    'happy'
                )
                ->firstOrFail();

        $profile->update([
            'current_mood' =>
                CharacterMood::Happy,

            'current_expression_id' =>
                $happy->id,
        ]);

        $this
            ->actingAs(
                $user
            )
            ->get('/chat')
            ->assertOk()
            ->assertSee(
                'Estado de ánimo'
            )
            ->assertSee(
                'Expresión actual'
            )
            ->assertSee(
                'Feliz'
            );
    }

    public function test_chat_displays_curiosity_state(): void
    {
        $this->seed(
            CharacterSeeder::class
        );

        $user =
            User::factory()
                ->create();

        $character =
            Character::query()
                ->where(
                    'slug',
                    'default-companion'
                )
                ->firstOrFail();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute(
            $user,
            $character
        );

        $curious =
            $character
                ->expressions()
                ->where(
                    'name',
                    'curious'
                )
                ->firstOrFail();

        $profile->update([
            'current_mood' =>
                CharacterMood::Curious,

            'current_expression_id' =>
                $curious->id,
        ]);

        $this
            ->actingAs(
                $user
            )
            ->get('/chat')
            ->assertOk()
            ->assertSee(
                'Curiosidad'
            );
    }
}
