<?php

namespace Tests\Feature\Chat;

use App\Actions\Characters\CreateUserCharacterProfileAction;
use App\Enums\CharacterMood;
use App\Enums\RelationshipStage;
use App\Models\Character;
use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(CharacterSeeder::class);
    }

    public function test_page_displays_character_and_profile_state(): void
    {
        $character = Character::query()
            ->where('slug', 'default-companion')
            ->firstOrFail();

        $user = User::factory()->create();

        $profile = app(
            CreateUserCharacterProfileAction::class
        )->execute($user, $character);

        $happyExpression = $character
            ->expressions()
            ->where('name', 'happy')
            ->firstOrFail();

        $profile->update([
            'current_mood' => CharacterMood::Happy,
            'current_expression_id' => $happyExpression->id,
            'relationship_stage' => RelationshipStage::Friends,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response
            ->assertOk()
            ->assertSee($character->name)
            ->assertSee($character->description)
            ->assertSee('Feliz')
            ->assertSee('Amigos')
            ->assertSee('Todavía no hay mensajes');
    }

    public function test_each_user_sees_their_own_profile(): void
    {
        $character = Character::query()
            ->where('slug', 'default-companion')
            ->firstOrFail();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $action = app(
            CreateUserCharacterProfileAction::class
        );

        $firstProfile = $action->execute(
            $firstUser,
            $character
        );

        $secondProfile = $action->execute(
            $secondUser,
            $character
        );

        $firstProfile->update([
            'nickname_for_character' => 'Compañero de Usuario A',
        ]);

        $secondProfile->update([
            'nickname_for_character' => 'Compañero de Usuario B',
        ]);

        $this
            ->actingAs($firstUser)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Compañero de Usuario A')
            ->assertDontSee('Compañero de Usuario B');

        $this
            ->actingAs($secondUser)
            ->get('/chat')
            ->assertOk()
            ->assertSee('Compañero de Usuario B')
            ->assertDontSee('Compañero de Usuario A');
    }

    public function test_chat_uses_the_active_character(): void
    {
        $inactiveCharacter = Character::factory()
            ->inactive()
            ->create([
                'name' => 'Inactive Companion',
            ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response
            ->assertOk()
            ->assertSee('Default Companion')
            ->assertDontSee($inactiveCharacter->name);
    }

    public function test_message_controls_are_present_but_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response
            ->assertOk()
            ->assertSee('Restablecer personaje')
            ->assertSee('Enviar')
            ->assertSee('disabled', false);
    }
}
