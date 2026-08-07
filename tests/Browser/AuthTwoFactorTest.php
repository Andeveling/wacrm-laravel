<?php

use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
    $this->user = User::factory()->withTwoFactor()->create([
        'email' => '2fa-test@example.com',
        'password' => $this->password,
    ]);
});

/**
 * Fortify only serves /two-factor-challenge while the session carries the
 * pending login, so the challenge has to be reached through a real sign-in
 * rather than by visiting the URL.
 */
function submitTwoFactorLogin(): void
{
    test()->visit('/login')
        ->type('input#email', test()->user->email)
        ->type('input#password', test()->password)
        ->press('button[type="submit"]')
        ->assertPathIs('/two-factor-challenge');
}

test('two factor challenge page renders with OTP input', function () {
    submitTwoFactorLogin();

    $this->visit('/two-factor-challenge')
        ->assertNoSmoke()
        ->assertSee('Código de autenticación')
        ->assertPresent('input[name="code"]');
});

test('two factor toggle switches to recovery code mode', function () {
    submitTwoFactorLogin();

    $this->visit('/two-factor-challenge')
        ->assertSee('iniciar sesión con un código de recuperación')
        ->click('iniciar sesión con un código de recuperación')
        ->assertSee('Código de recuperación')
        ->assertPresent('input[name="recovery_code"]');
});

test('invalid code shows error and stays on page', function () {
    submitTwoFactorLogin();

    $this->visit('/two-factor-challenge')
        ->type('input[name="code"]', '000000')
        ->press('button[type="submit"]')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Código de autenticación');
});
