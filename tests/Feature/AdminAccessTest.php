<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_and_back_to_the_panel_after_logging_in_as_admin(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'Secreto#123']);

        $this->get('/admin')->assertRedirect('/login');

        $this->post('/login', ['email' => $admin->email, 'password' => 'Secreto#123'])
            ->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('admin-root', false);
    }

    public function test_regular_user_gets_access_denied(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden()
            ->assertSee('Acceso denegado')
            ->assertSee('Solo los administradores pueden entrar a esta sección.');
    }

    public function test_admin_goes_to_the_panel_after_logging_in(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'Secreto#123']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'Secreto#123'])
            ->assertRedirect('/admin');
    }

    public function test_regular_user_goes_to_the_catalog_after_logging_in(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123'])
            ->assertRedirect('/dashboard');
    }

    public function test_new_users_are_regular_users_even_if_they_send_a_role(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'Secreto#123',
            'password_confirmation' => 'Secreto#123',
            'role' => 'admin',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertSame(UserRole::User, $user->role);
        $this->get('/admin')->assertForbidden();
    }

    public function test_catalog_shows_the_admin_link_only_to_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSee('&quot;isAdmin&quot;:true', false);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertSee('&quot;isAdmin&quot;:false', false);
    }

    public function test_demoting_an_admin_takes_effect_on_the_next_request(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'Secreto#123']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'Secreto#123']);
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertOk();

        $this->artisan('user:role', ['email' => $admin->email, 'role' => 'user'])->assertSuccessful();
        $this->app['auth']->forgetGuards();

        $this->get('/admin')->assertForbidden();
    }

    public function test_role_command_promotes_a_user(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('user:role', ['email' => 'ANA@example.com'])
            ->expectsOutput('ana@example.com ahora es Administrador (admin).')
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_role_command_rejects_unknown_users_and_roles(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->artisan('user:role', ['email' => 'nadie@example.com'])->assertFailed();
        $this->artisan('user:role', ['email' => 'ana@example.com', 'role' => 'superadmin'])->assertFailed();
    }
}
