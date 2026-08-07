<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Tag;
use App\Models\User;

const CONTACTS_SEARCH = 'input[placeholder="Buscar por nombre, teléfono o correo…"]';

beforeEach(function () {
    $this->password = 'password';
    $this->owner = User::factory()->create(['password' => $this->password]);
    $this->account = Account::factory()->create(['type' => AccountType::Team]);

    AccountUser::create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
        'role' => AccountRole::Owner,
    ]);
    $vip = Tag::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
        'name' => 'VIP',
        'color' => '#f59e0b',
    ]);

    for ($index = 0; $index < 24; $index++) {
        $contact = Contact::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'name' => in_array($index, [0, 8, 16], true)
                ? 'Laura Gómez'
                : "Contacto {$index}",
        ]);

        if ($index < 8) {
            ContactTag::create([
                'contact_id' => $contact->id,
                'tag_id' => $vip->id,
            ]);
        }
    }
});

test('contacts list filters through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->assertNoSmoke()
        ->assertSee('Contactos')
        ->type(CONTACTS_SEARCH, 'Laura Gómez')
        ->assertSee('3 contactos')
        ->assertSee('Laura Gómez');
});

test('contacts list paginates, filters tags, and selects visible rows', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->assertNoSmoke()
        ->click('[data-testid="contacts-next-page"]')
        ->assertSee('Mostrando 11–20 de 24')
        ->assertSee('Página 2 de 3')
        ->click('[data-testid="contacts-previous-page"]')
        ->click('[data-testid="contacts-tag-filter"]')
        ->assertVisible('[data-testid="contacts-tag-vip"]')
        ->click('[data-testid="contacts-tag-vip"]')
        ->assertSee('8 contactos')
        ->click('thead [data-slot="checkbox"]')
        ->assertSee('8 seleccionados');
});

test('contacts page creates through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->click('[data-testid="contacts-add"]')
        ->assertSee('Nuevo contacto')
        ->type('#cf-name', 'Sofía Méndez')
        ->type('#cf-phone', '+57 300 999 9999')
        ->type('#cf-email', 'sofia@example.com')
        ->press('button[type="submit"]')
        ->assertSee('Sofía Méndez');

    expect(Contact::query()->where('name', 'Sofía Méndez')->exists())->toBeTrue();
});

test('contacts page edits through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->type(CONTACTS_SEARCH, 'Laura Gómez')
        ->click('[data-testid="contact-actions-row-0"]')
        ->assertVisible('[data-testid="contact-edit-row-0"]')
        ->click('[data-testid="contact-edit-row-0"]')
        ->assertSee('Editar contacto')
        ->type('#cf-name', 'Laura Gómez editado')
        ->press('button[type="submit"]')
        ->assertSee('Laura Gómez editado');

    expect(Contact::query()->where('name', 'Laura Gómez editado')->exists())->toBeTrue();
});

test('contacts page deletes a contact through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->assertNoSmoke()
        ->type(CONTACTS_SEARCH, 'Contacto 5')
        ->assertSee('1 contactos')
        ->click('[data-testid="contact-actions-row-0"]')
        ->assertVisible('[data-testid="contact-delete-row-0"]')
        ->click('[data-testid="contact-delete-row-0"]')
        ->assertSee('¿Eliminar a Contacto 5?')
        ->assertVisible('[data-testid="contacts-delete-confirm"]')
        ->click('[data-testid="contacts-delete-confirm"]')
        ->assertSee('Contacto eliminado.')
        // Deleting redirects to the unfiltered index, so the roster comes back
        // with every remaining contact even though the search box still holds
        // the term that was typed.
        ->assertSee('23 contactos')
        ->assertDontSee('Contacto 5');

    expect(Contact::query()->count())->toBe(23);
});

test('contacts page bulk deletes the selected rows through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->assertNoSmoke()
        ->click('thead [data-slot="checkbox"]')
        ->assertSee('10 seleccionados')
        ->click('[data-testid="contacts-bulk-delete"]')
        ->assertSee('¿Eliminar 10 contactos seleccionados?')
        ->assertVisible('[data-testid="contacts-bulk-delete-confirm"]')
        ->click('[data-testid="contacts-bulk-delete-confirm"]')
        ->assertSee('10 contactos eliminados.')
        ->assertSee('14 contactos')
        ->assertDontSee('seleccionados');

    expect(Contact::query()->count())->toBe(14);
});
