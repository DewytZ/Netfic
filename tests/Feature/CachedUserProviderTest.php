<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CachedUserProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_user_is_not_queried_on_every_request(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);
        // Al iniciar sesión el usuario queda en caché: ni la primera carga del panel lo consulta.
        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123']);

        $this->app['auth']->forgetGuards();
        $userQueries = 0;
        DB::listen(function ($query) use (&$userQueries) {
            if (str_contains($query->sql, '"users"')) {
                $userQueries++;
            }
        });

        $this->get('/dashboard')->assertOk()->assertSee($user->name);

        $this->assertSame(0, $userQueries);
    }

    public function test_cached_user_is_refreshed_when_it_changes(): void
    {
        $user = User::factory()->create(['name' => 'Ana', 'password' => 'Secreto#123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123']);
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertSee('Ana');

        $user->update(['name' => 'Ana María']);
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard')->assertSee('Ana María');
    }

    public function test_deleted_user_loses_the_session(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123']);
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertOk();

        $user->delete();
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_remember_token_must_match(): void
    {
        $user = User::factory()->create(['remember_token' => 'token-correcto']);
        $provider = Auth::createUserProvider('users');

        $this->assertTrue($provider->retrieveByToken($user->id, 'token-correcto')->is($user));
        $this->assertNull($provider->retrieveByToken($user->id, 'token-incorrecto'));
    }
}
