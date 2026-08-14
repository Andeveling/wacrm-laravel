<?php

use App\Models\User;

test('registration uses the shared public auth form presentation', function () {
    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/register')
        ->assertSee('Crear una cuenta')
        ->assertSee('Nombre')
        ->assertSee('Correo electrónico')
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=auth-form-panel]')).maxWidth === '448px'")
        ->assertScript("getComputedStyle(document.querySelector('input[name=name]')).height === '48px'")
        ->assertNoSmoke();
});

test('email verification preserves the shared public auth presentation', function () {
    $password = 'password';
    $user = User::factory()->create(['password' => $password]);

    signIn($user, $password);

    /** @phpstan-ignore-next-line Browser visit is supplied by Pest at runtime. */
    $this->visit('/email/verify')
        ->assertSee('Verifica tu correo electrónico')
        ->assertSee('Reenviar correo de verificación')
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=auth-form-panel]')).maxWidth === '448px'")
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=button]')).height === '48px'")
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=button]')).fontSize === '16px'")
        ->assertNoSmoke();
});
