<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_emailed_to_registered_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_email_uses_the_netfic_design_and_contains_the_link(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $mail = (new ResetPassword('token-de-prueba'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Restablecer contraseña', $mail->subject);
        $this->assertStringContainsString('Hola Ana', $html);
        $this->assertStringContainsString(route('password.reset', ['token' => 'token-de-prueba', 'email' => $user->email]), html_entity_decode($html));
        $this->assertStringContainsString('expira en 60 minutos', $html);
    }

    public function test_unknown_email_gets_same_response_and_no_email(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nadie@example.com'])
            ->assertSessionHas('status', 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña.')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_does_not_send_another_email_to_the_same_address_within_a_minute(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_user_can_reset_password_and_other_sessions_are_closed(): void
    {
        $user = User::factory()->create(['password' => 'Vieja#Clave1']);

        // Otra sesión (otro dispositivo) que inició sesión con la contraseña anterior.
        $this->post('/login', ['email' => $user->email, 'password' => 'Vieja#Clave1']);
        $this->app['auth']->forgetGuards();
        $this->get('/dashboard')->assertOk();
        $oldSession = session()->all();
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $token = Password::createToken($user);

        $this->get("/reset-password/{$token}?email={$user->email}")
            ->assertOk()
            ->assertSee('Nueva contraseña');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Nueva#Clave9',
            'password_confirmation' => 'Nueva#Clave9',
        ])->assertRedirect('/login')->assertSessionHas('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');

        $this->assertTrue(Hash::check('Nueva#Clave9', $user->fresh()->password));

        // La sesión anterior guardaba el hash de la contraseña vieja: AuthenticateSession la cierra.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withSession($oldSession)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();

        // El enlace ya no sirve una segunda vez.
        $this->get("/reset-password/{$token}?email={$user->email}")->assertSee('Enlace no válido');
    }

    public function test_reset_rejects_weak_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'debil',
            'password_confirmation' => 'debil',
        ])->assertRedirect("/reset-password/{$token}")->assertSessionHasErrors('password');
    }

    public function test_link_with_fake_token_shows_invalid_link_without_form(): void
    {
        $user = User::factory()->create();

        $this->get("/reset-password/token-falso?email={$user->email}")
            ->assertOk()
            ->assertSee('Enlace no válido')
            ->assertDontSee('resetPasswordForm');
    }

    public function test_fake_token_posted_directly_to_the_form_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Vieja#Clave1']);

        $this->post('/reset-password', [
            'token' => 'token-falso',
            'email' => $user->email,
            'password' => 'Nueva#Clave9',
            'password_confirmation' => 'Nueva#Clave9',
        ])->assertRedirect('/reset-password/token-falso')
            ->assertSessionHasErrors(['email' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.']);

        $this->assertTrue(Hash::check('Vieja#Clave1', $user->fresh()->password));
    }

    public function test_link_works_even_with_another_session_open_in_the_browser(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = Password::createToken($user);

        $this->actingAs($otherUser)
            ->get("/reset-password/{$token}?email={$user->email}")
            ->assertOk()
            ->assertSee('Nueva contraseña');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Nueva#Clave9',
            'password_confirmation' => 'Nueva#Clave9',
        ])->assertRedirect('/login');

        // La sesión que estaba abierta se cierra para entrar con la cuenta recién cambiada.
        $this->assertGuest();
    }
}
