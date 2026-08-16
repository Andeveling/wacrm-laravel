<?php

use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
    $this->user = User::factory()->withTwoFactor()->create([
        'email' => '2fa-test@example.com',
        'password' => $this->password,
    ]);
});

/*
 * Fortify only serves /two-factor-challenge while the session carries the
 * pending login, so every test here signs in first: visiting the URL cold
 * redirects to /login and the challenge never renders.
 */

test('two factor challenge page renders with OTP input', function () {
    signIn($this->user, $this->password);

    $this->visit('/two-factor-challenge')
        ->assertNoSmoke()
        ->assertSee('Código de autenticación')
        ->assertPresent('input[name="code"]')
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=two-factor-otp-slot]')).height === '48px'")
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=two-factor-otp-slot]')).fontSize === '16px'")
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=two-factor-recovery-toggle]')).height === '48px'");
});

test('two factor toggle switches to recovery code mode', function () {
    signIn($this->user, $this->password);

    $this->visit('/two-factor-challenge')
        ->assertSee('iniciar sesión con un código de recuperación')
        ->click('iniciar sesión con un código de recuperación')
        ->assertSee('Código de recuperación')
        ->assertPresent('input[name="recovery_code"]');
});

test('invalid code shows error and stays on page', function () {
    signIn($this->user, $this->password);

    $this->visit('/two-factor-challenge')
        ->type('input[name="code"]', '000000')
        ->press('button[type="submit"]')
        ->assertPathIs('/two-factor-challenge')
        ->assertSee('Código de autenticación');
});
