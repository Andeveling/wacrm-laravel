<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Tag;
use App\Models\User;

const CONTACTS_SEARCH_INPUT = 'input[placeholder="Buscar por nombre, teléfono o correo…"]';

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
        ->type(CONTACTS_SEARCH_INPUT, 'Laura Gómez')
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
        ->type('[data-testid="contact-form-name"]', 'Sofía Méndez')
        ->type('[data-testid="contact-form-phone"]', '+57 300 999 9999')
        ->type('[data-testid="contact-form-email"]', 'sofia@example.com')
        ->press('button[type="submit"]')
        ->assertSee('Sofía Méndez');

    expect(Contact::query()->where('name', 'Sofía Méndez')->exists())->toBeTrue();
});

test('contacts page edits through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->type(CONTACTS_SEARCH_INPUT, 'Laura Gómez')
        ->click('[data-testid="contact-actions-row-0"]')
        ->assertVisible('[data-testid="contact-edit-row-0"]')
        ->click('[data-testid="contact-edit-row-0"]')
        ->assertSee('Editar contacto')
        ->type('[data-testid="contact-form-name"]', 'Laura Gómez editado')
        ->press('button[type="submit"]')
        ->assertSee('Laura Gómez editado');

    expect(Contact::query()->where('name', 'Laura Gómez editado')->exists())->toBeTrue();
});

test('contacts detail edit preserves the filtered page and page size', function () {
    $target = Contact::factory()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
        'name' => 'Contacto objetivo',
        'created_at' => now()->addDay(),
    ]);

    for ($index = 0; $index < 10; $index++) {
        Contact::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'name' => "Contacto posterior {$index}",
            'created_at' => now()->addDays(2)->addSeconds($index),
        ]);
    }

    signInAndSelectAccount($this->owner);

    $this->visit('/contacts?search=Contacto&per_page=10&page=2')
        ->assertNoSmoke()
        ->assertSee('Mostrando 11–20 de 32')
        ->assertSee('Página 2 de 4')
        ->assertSeeIn('[data-testid="contact-row-0"]', 'Contacto objetivo')
        ->click('[data-testid="contact-row-0"]')
        ->assertSee('Detalles del contacto')
        ->fill('[data-testid="contact-detail-name"]', '  Contacto actualizado  ')
        ->press('Guardar cambios')
        ->assertSee('Contacto actualizado.')
        ->assertSeeIn('[data-testid="contact-row-0"]', 'Contacto actualizado')
        ->assertSeeIn('[data-testid="contact-detail-title"]', 'Contacto actualizado')
        ->assertQueryStringHas('search', 'Contacto')
        ->assertQueryStringHas('per_page', '10')
        ->assertQueryStringHas('page', '2')
        ->assertSee('Mostrando 11–20 de 32')
        ->assertSee('Página 2 de 4');

    expect($target->fresh()->name)->toBe('Contacto actualizado');
});

test('contacts detail stays open when an edit removes the contact from the filter', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts?search=Laura%20G%C3%B3mez')
        ->type(CONTACTS_SEARCH_INPUT, 'Laura Gómez')
        ->click('[data-testid="contact-row-0"]')
        ->fill('[data-testid="contact-detail-name"]', 'Nombre fuera del filtro')
        ->press('Guardar cambios')
        ->assertSee('Contacto actualizado.')
        ->assertSee('2 contactos')
        ->assertSeeIn('[data-testid="contact-detail-title"]', 'Nombre fuera del filtro');

    expect(Contact::query()->where('name', 'Nombre fuera del filtro')->exists())->toBeTrue();
});

// Deleting redirects to the unfiltered index, so the roster comes back whole
// while the search box still shows the term that was typed.
test('contacts page deletes a contact through the page seam', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->assertNoSmoke()
        ->type(CONTACTS_SEARCH_INPUT, 'Contacto 5')
        ->assertSee('1 contactos')
        ->click('[data-testid="contact-actions-row-0"]')
        ->assertVisible('[data-testid="contact-delete-row-0"]')
        ->click('[data-testid="contact-delete-row-0"]')
        ->assertSee('¿Eliminar a Contacto 5?')
        ->assertVisible('[data-testid="contacts-delete-confirm"]')
        ->click('[data-testid="contacts-delete-confirm"]')
        ->assertSee('Contacto eliminado.')
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

test('contacts page bulk deletes selections retained across pagination', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->click('thead [data-slot="checkbox"]')
        ->assertSee('10 seleccionados')
        ->click('[data-testid="contacts-next-page"]')
        ->assertSee('Página 2 de 3')
        ->click('[data-testid="contacts-bulk-delete"]')
        ->assertSee('¿Eliminar 10 contactos seleccionados?')
        ->click('[data-testid="contacts-bulk-delete-confirm"]')
        ->assertSee('10 contactos eliminados.')
        ->assertSee('14 contactos')
        ->assertDontSee('seleccionados');

    expect(Contact::query()->count())->toBe(14);
});

test('contacts page bulk deletes selections retained after filtering', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/contacts')
        ->click('thead [data-slot="checkbox"]')
        ->assertSee('10 seleccionados')
        ->type(CONTACTS_SEARCH_INPUT, 'Laura Gómez')
        ->assertSee('3 contactos')
        ->click('[data-testid="contacts-bulk-delete"]')
        ->assertSee('¿Eliminar 10 contactos seleccionados?')
        ->click('[data-testid="contacts-bulk-delete-confirm"]')
        ->assertSee('10 contactos eliminados.')
        ->assertSee('14 contactos')
        ->assertDontSee('seleccionados');

    expect(Contact::query()->count())->toBe(14);
});
