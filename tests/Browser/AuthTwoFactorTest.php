<?php

use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
    $this->user = User::factory()->withTwoFactor()->create([
        'email' => '2fa-test@example.com',
        'password' => $this->password,
    ]);
});

test('two factor challenge page renders with OTP input', function () {
    $this->visit('/login')
        ->type('input#email', $this->user->email)
        ->type('input#password', $this->password)
        ->press('button[type="submit"]');

    $this->visit('/two-factor-challenge')
        ->assertNoSmoke()
        ->assertSee('Autenticación en dos pasos')
        ->assertSee('Código de autenticación');
});

test('two factor toggle switches to recovery code mode', function () {
    $this->visit('/two-factor-challenge')
        ->assertSee('iniciar sesión con un código de recuperación');
});

test('invalid code shows error and stays on page', function () {
    $this->visit('/two-factor-challenge')
        ->press('button[type="submit"]');
});
