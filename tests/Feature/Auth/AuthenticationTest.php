<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $usuario = Usuario::factory()->create();

        $response = $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $usuario = Usuario::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $usuario->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'El correo electrónico o la contraseña son incorrectos.',
        ]);
    }

    public function test_login_validation_errors_are_in_spanish(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
    }

    public function test_users_can_logout(): void
    {
        $usuario = Usuario::factory()->create();

        $response = $this->actingAs($usuario)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
