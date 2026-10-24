<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API de autenticación para la app Android (Sanctum, token Bearer).
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_validates_and_returns_a_token(): void
    {
        $this->postJson('/api/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'corta'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Secreto#123'])
            ->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_login_user_and_logout_with_the_token(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'otra'])->assertUnauthorized();

        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secreto#123'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('email', $user->email);
        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_requires_a_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
