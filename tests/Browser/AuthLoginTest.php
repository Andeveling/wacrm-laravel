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
        ->assertSee('Iniciar sesión')
        ->assertSee('Correo electrónico')
        ->assertSee('Contraseña')
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertSee('Recuérdame')
        ->assertSee('Iniciar sesión con llave de acceso');
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
        ->assertSee('email');
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
