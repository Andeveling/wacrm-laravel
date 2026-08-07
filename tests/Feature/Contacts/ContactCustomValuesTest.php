<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactCustomValue;
use App\Models\CustomField;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a member can save custom field values without duplicating on a second save', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();
    $field = CustomField::factory()->for($account)->create(['field_name' => 'Fuente']);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-values.store', $contact), [
            'values' => [$field->id => 'Instagram'],
        ])
        ->assertRedirect(route('contacts'));

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-values.store', $contact), [
            'values' => [$field->id => 'Facebook'],
        ])
        ->assertRedirect(route('contacts'));

    $values = ContactCustomValue::withoutGlobalScopes()
        ->where('contact_id', $contact->id)
        ->where('custom_field_id', $field->id)
        ->get();

    expect($values)->toHaveCount(1)
        ->and($values->first()->value)->toBe('Facebook');
});

test('reading custom values returns a map keyed by custom field id', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();
    $field = CustomField::factory()->for($account)->create();
    ContactCustomValue::factory()->for($contact)->for($field)->create(['value' => 'Bogotá']);

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.custom-values', $contact));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('contacts')
        ->where("customValues.{$field->id}", 'Bogotá'));
});

test('a viewer can read custom values but cannot write them', function () {
    [$viewer, $account] = memberWithRole('viewer');
    $contact = Contact::factory()->for($account)->create();
    $field = CustomField::factory()->for($account)->create();

    $this->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.custom-values', $contact))
        ->assertOk();

    $this->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-values.store', $contact), ['values' => [$field->id => 'No permitido']])
        ->assertForbidden();
});

test('custom values are isolated per tenant', function () {
    [$member, $account] = memberWithRole('member');
    $foreignAccount = Account::factory()->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.custom-values', $foreignContact))
        ->assertNotFound();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-values.store', $foreignContact), ['values' => []])
        ->assertNotFound();
});

test('saving a custom value for a field from another account is rejected', function () {
    [$member, $account] = memberWithRole('member');
    $contact = Contact::factory()->for($account)->create();
    $foreignAccount = Account::factory()->create();
    $foreignField = CustomField::factory()->for($foreignAccount)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-values.store', $contact), [
            'values' => [$foreignField->id => 'Intruso'],
        ])
        ->assertSessionHasErrors('values');
});
