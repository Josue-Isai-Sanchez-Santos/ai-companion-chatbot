<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSee('Crear cuenta');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Usuario de prueba',
            'email' => 'usuario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/chat');

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => 'Usuario de prueba',
            'email' => 'usuario@example.com',
        ]);
    }

    public function test_registration_requires_unique_email(): void
    {
        $this->post('/register', [
            'name' => 'Primer usuario',
            'email' => 'duplicado@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->post('/logout');

        $response = $this->from('/register')->post('/register', [
            'name' => 'Segundo usuario',
            'email' => 'duplicado@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }
}
