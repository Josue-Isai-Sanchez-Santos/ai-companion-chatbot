<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\CharacterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login_from_chat(): void
    {
        $response = $this->get('/chat');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_chat(): void
    {
        $this->seed(CharacterSeeder::class);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response
            ->assertOk()
            ->assertSee('Default Companion')
            ->assertSee('Conversación')
            ->assertSee('Configuración')
            ->assertSee('Restablecer personaje')
            ->assertSee('Ninguna conversación seleccionada');
    }
}
