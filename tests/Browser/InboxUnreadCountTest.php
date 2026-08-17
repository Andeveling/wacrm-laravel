<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappPhoneNumberConnection;

test('a member opening a thread keeps the unread badge at zero after reload', function () {
    [$member, $account] = memberWithRole('member');
    WhatsappPhoneNumberConnection::factory()->for($account)->create();
    $first = Contact::factory()->for($account)->create(['name' => 'Ana Pérez']);
    $second = Contact::factory()->for($account)->create(['name' => 'Luis Gómez']);
    $ana = Conversation::factory()->for($account)->create([
        'contact_id' => $first->id,
        'user_id' => $member->id,
        'unread_count' => 3,
        'last_message_text' => '¿Sigues ahí?',
        'last_message_at' => now()->subHours(2),
    ]);
    $luis = Conversation::factory()->for($account)->create([
        'contact_id' => $second->id,
        'user_id' => $member->id,
        'unread_count' => 9,
        'last_message_text' => 'Necesito ayuda.',
        'last_message_at' => now()->subHours(3),
    ]);

    signInAndSelectAccount($member);

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->assertSee('Luis Gómez')
        ->assertNotPresent('[data-testid="unread-count-'.$ana->id.'"]')
        ->assertPresent('[data-testid="unread-count-'.$luis->id.'"]');

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->assertNotPresent('[data-testid="unread-count-'.$ana->id.'"]')
        ->click('button:has-text("Luis Gómez")')
        ->assertNotPresent('[data-testid="unread-count-'.$luis->id.'"]');

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertNotPresent('[data-testid="unread-count-'.$ana->id.'"]')
        ->assertNotPresent('[data-testid="unread-count-'.$luis->id.'"]');
});

test('a viewer opening a thread keeps the unread badge after reload', function () {
    [$viewer, $account] = memberWithRole('viewer');
    WhatsappPhoneNumberConnection::factory()->for($account)->create();
    $contact = Contact::factory()->for($account)->create(['name' => 'Ana Pérez']);
    $conversation = Conversation::factory()->for($account)->create([
        'contact_id' => $contact->id,
        'user_id' => $viewer->id,
        'unread_count' => 3,
        'last_message_text' => '¿Sigues ahí?',
        'last_message_at' => now()->subHours(2),
    ]);

    signInAndSelectAccount($viewer);

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->assertSeeIn('[data-testid="unread-count-'.$conversation->id.'"]', '3');

    $this->visit('/inbox')
        ->assertNoSmoke()
        ->assertSee('Ana Pérez')
        ->assertSeeIn('[data-testid="unread-count-'.$conversation->id.'"]', '3');
});
