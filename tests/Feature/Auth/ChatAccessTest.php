<?php

namespace Tests\Feature\Auth;

use App\Models\User;
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
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/chat');

        $response
            ->assertOk()
            ->assertSee('Sesión autenticada')
            ->assertSee($user->name)
            ->assertSee($user->email);
    }
}
