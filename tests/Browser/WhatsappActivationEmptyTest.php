<?php

test('an admin without connections sees the activation empty and cta on inbox', function () {
    [$admin] = memberWithRole('admin');

    signInAndSelectAccount($admin);

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Inbox')
        ->assertSee('Conectá tu primer número de WhatsApp')
        ->click('a:has-text("Conectá tu primer número de WhatsApp")')
        ->assertPathIs('/settings/whatsapp')
        ->assertSee('Conectar primer número');
});

test('a member without connections sees the activation empty without a cta on inbox', function () {
    [$member] = memberWithRole('member');

    signInAndSelectAccount($member);

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Inbox')
        ->assertSee('Pedile a un admin que conecte un número')
        ->assertDontSee('Conectá tu primer número de WhatsApp');
});
