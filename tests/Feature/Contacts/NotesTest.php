<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('a member can create and delete a note on a contact', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.notes.store', $contact), [
            'note_text' => 'Llamó para pedir soporte.',
        ])
        ->assertRedirect(route('contacts'));

    $note = ContactNote::withoutGlobalScopes()->where('contact_id', $contact->id)->sole();
    expect($note->note_text)->toBe('Llamó para pedir soporte.')
        ->and($note->user_id)->toBe($member->id);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.notes.destroy', $note))
        ->assertRedirect(route('contacts'));

    expect(ContactNote::withoutGlobalScopes()->find($note->id))->toBeNull();
});

test('reading notes returns the note author', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();
    ContactNote::factory()->for($contact)->create([
        'account_id' => $account->id,
        'user_id' => $member->id,
        'note_text' => 'Contexto importante.',
    ]);

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.notes', $contact));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('contacts')
        ->has('notes', 1)
        ->where('notes.0.note_text', 'Contexto importante.')
        ->where('notes.0.user.name', $member->name));
});

test('a viewer can read notes but cannot write them', function () {
    [$viewer, $account] = memberWithRole('viewer');
    $contact = Contact::factory()->for($account)->create();
    ContactNote::factory()->for($contact)->create(['account_id' => $account->id]);

    $this->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.notes', $contact))
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.notes.store', $contact), ['note_text' => 'No permitido'])
        ->assertForbidden();
});

test('notes are isolated per tenant', function () {
    [$member, $account] = memberWithRole('member');
    $foreignAccount = Account::factory()->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();
    $foreignNote = ContactNote::factory()->for($foreignContact)->create(['account_id' => $foreignAccount->id]);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.notes', $foreignContact))
        ->assertNotFound();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.notes.store', $foreignContact), ['note_text' => 'No debe cruzar cuentas'])
        ->assertNotFound();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.notes.destroy', $foreignNote))
        ->assertNotFound();

    expect(ContactNote::withoutGlobalScopes()->find($foreignNote->id))->not->toBeNull();
});

test('reading notes loads the author without a query per note', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();
    ContactNote::factory()->for($contact)->count(3)->create(['account_id' => $account->id, 'user_id' => $member->id]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from ["`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['contact_notes', 'users'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.notes', $contact))
        ->assertOk();

    expect(array_count_values($queries))->toMatchArray([
        'contact_notes' => 1,
        'users' => 1,
    ]);
});
