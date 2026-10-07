<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_register(): void
    {
        $this->post('/register', [
            'name' => 'Ana',
            'email' => 'Ana@Example.com',
            'password' => 'Secreto#123',
            'password_confirmation' => 'Secreto#123',
        ])->assertRedirect('/dashboard')->assertSessionHas('status', 'Cuenta creada correctamente.');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_register_rejects_an_email_already_registered(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->post('/register', [
            'name' => 'Otra',
            'email' => 'ana@example.com',
            'password' => 'Secreto#123',
            'password_confirmation' => 'Secreto#123',
        ])->assertSessionHasErrors(['email' => 'Ese email ya está registrado.']);

        $this->assertGuest();
    }

    #[DataProvider('weakPasswords')]
    public function test_register_rejects_weak_passwords(string $password): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'muy corta' => ['Ab#1'],
            'sin mayúscula' => ['secreto#123'],
            'sin minúscula' => ['SECRETO#123'],
            'sin número' => ['Secreto#abc'],
            'sin símbolo' => ['Secreto1234'],
            'con espacio' => ['Secreto #123'],
        ];
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertOk()->assertSee($user->name);

        $this->post('/logout')->assertRedirect('/login')->assertSessionHas('status', 'Sesión cerrada.');
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Incorrecta#1'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Las credenciales no son correctas.']);

        $this->assertGuest();
    }

    public function test_after_login_user_returns_to_the_page_they_wanted(): void
    {
        $user = User::factory()->create(['password' => 'Secreto#123']);
        $this->get('/dashboard');

        $this->post('/login', ['email' => $user->email, 'password' => 'Secreto#123'])
            ->assertRedirect('/dashboard');
    }

    public function test_login_is_limited_to_five_attempts_per_minute(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'Incorrecta#1'])->assertRedirect('/login');
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'Incorrecta#1'])
            ->assertStatus(429)
            ->assertSee('Demasiados intentos');
    }

    public function test_users_created_by_the_node_version_can_login_after_migrating(): void
    {
        // bcryptjs (Node) guarda los hash con el prefijo $2b$, que Laravel rechaza.
        $nodeHash = '$2b$'.substr(password_hash('Secreto#123', PASSWORD_BCRYPT), 4);
        DB::table('users')->insert([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => $nodeHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/'.collect(scandir(database_path('migrations')))
            ->first(fn (string $file) => str_ends_with($file, '_adapt_existing_users_table.php')));
        $migration->up();

        $this->assertStringStartsWith('$2y$', DB::table('users')->value('password'));
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'Secreto#123'])
            ->assertRedirect('/dashboard');
    }
}
