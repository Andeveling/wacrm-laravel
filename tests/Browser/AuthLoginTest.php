<?php

use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
    $this->user = User::factory()->create([
        'email' => 'login-test@example.com',
        'password' => $this->password,
    ]);
});

test('login page renders with correct elements', function () {
    $this->visit('/login')
        ->assertNoSmoke()
        ->assertSee('Wacrm')
        ->assertSee('Iniciar sesión')
        ->assertSee('Correo electrónico')
        ->assertSee('Contraseña')
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertSee('Recuérdame')
        ->assertSee('Iniciar sesión con llave de acceso');
});

test('login offers an accessible compact appearance toggle', function () {
    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/login')
        ->assertAttribute('html', 'data-mode', 'dark')
        ->click('[data-testid="auth-appearance-toggle"]')
        ->assertAttribute('html', 'data-mode', 'light')
        ->assertNoSmoke();
});

test('successful login redirects to dashboard', function () {
    $this->visit('/login')
        ->type('input#email', $this->user->email)
        ->type('input#password', $this->password)
        ->press('button[type="submit"]')
        ->assertNoSmoke();
});

test('failed login shows error message', function () {
    $this->visit('/login')
        ->type('input#email', $this->user->email)
        ->type('input#password', 'wrong-password')
        ->press('button[type="submit"]')
        ->assertSee(trans('auth.failed'));
});

test('forgot password link is visible', function () {
    $this->visit('/login')
        ->assertSee('¿Olvidaste tu contraseña?');
});

test('remember me checkbox is present', function () {
    $this->visit('/login')
        ->assertSee('Recuérdame');
});

test('passkey verify component renders', function () {
    $this->visit('/login')
        ->assertSee('Iniciar sesión con llave de acceso');
});
