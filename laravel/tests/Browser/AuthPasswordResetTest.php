<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->password = 'password';
    $this->user = User::factory()->create([
        'email' => 'reset-test@example.com',
        'password' => $this->password,
    ]);
});

test('forgot password page renders with email field and button', function () {
    $this->visit('/forgot-password')
        ->assertNoSmoke()
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertSee('Correo electrónico')
        ->assertSee('Enviar enlace de restablecimiento');
});

test('submit forgot password shows success message', function () {
    $this->visit('/forgot-password')
        ->type('input#email', $this->user->email)
        ->click('[data-test="email-password-reset-link-button"]')
        ->assertSee('Se ha enviado un nuevo enlace');
});

test('reset password page renders with email prefilled and readonly', function () {
    $token = Password::createToken($this->user);

    $this->visit('/reset-password/' . $token)
        ->assertNoSmoke()
        ->assertSee('Restablecer contraseña')
        ->assertSee('Contraseña')
        ->assertSee('Confirmar contraseña');
});

test('reset with valid token redirects to login', function () {
    $token = Password::createToken($this->user);

    $this->visit('/reset-password/' . $token)
        ->type('input#password', 'new-password-123')
        ->type('input#password_confirmation', 'new-password-123')
        ->click('[data-test="reset-password-button"]');
});

test('reset with invalid token shows error', function () {
    $this->visit('/reset-password/invalid-token')
        ->assertSee('email');
});
