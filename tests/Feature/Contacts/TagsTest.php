<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\CustomField;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('settings fields screen is tenant scoped and includes tags and custom fields', function () {
    [$admin, $account] = memberWithRole('admin');
    $foreignAccount = Account::factory()->create();
    Tag::factory()->for($account)->create(['name' => 'VIP']);
    Tag::factory()->for($foreignAccount)->create(['name' => 'Ajena']);
    CustomField::factory()->for($account)->create(['field_name' => 'Fuente']);

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.fields'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('settings/fields')
        ->has('tags', 1)
        ->where('tags.0.name', 'VIP')
        ->has('customFields', 1)
        ->where('canManage', true));
});

test('admins manage tags while members cannot', function () {
    [$admin, $account] = memberWithRole('admin');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.tags.store'), [
            'name' => 'VIP',
            'color' => '#f59e0b',
        ])
        ->assertRedirect();

    $tag = Tag::withoutGlobalScopes()->where('account_id', $account->id)->sole();
    expect($tag->name)->toBe('VIP')->and($tag->color)->toBe('#f59e0b');

    [$member, $memberAccount] = memberWithRole('member');
    $this->actingAs($member)
        ->withSession(['current_account_id' => $memberAccount->id])
        ->post(route('contacts.tags.store'), [
            'name' => 'No permitido',
            'color' => '#3b82f6',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('contacts.tags.update', $tag->id), [
            'name' => 'VIP renombrada',
            'color' => '#10b981',
        ])
        ->assertRedirect();

    expect($tag->fresh()->name)->toBe('VIP renombrada')
        ->and($tag->fresh()->color)->toBe('#10b981');

    $this->actingAs($member)
        ->withSession(['current_account_id' => $memberAccount->id])
        ->patch(route('contacts.tags.update', $tag->id), [
            'name' => 'Otra',
            'color' => '#3b82f6',
        ])
        ->assertForbidden();
});

test('tags are isolated per tenant', function () {
    [$admin, $account] = memberWithRole('admin');
    $foreignAccount = Account::factory()->create();
    $foreignTag = Tag::factory()->for($foreignAccount)->create();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('contacts.tags.update', $foreignTag->id), [
            'name' => 'Secuestrada',
            'color' => '#3b82f6',
        ])
        ->assertNotFound();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.tags.destroy', $foreignTag->id))
        ->assertNotFound();

    expect(Tag::withoutGlobalScopes()->find($foreignTag->id))->not->toBeNull();
});

test('deleting a tag in use detaches it from every contact', function () {
    [$admin, $account] = memberWithRole('admin');
    $tag = Tag::factory()->for($account)->create();
    $contact = Contact::factory()->for($account)->create(['user_id' => $admin->id]);
    ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tag->id]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.tags.destroy', $tag->id))
        ->assertRedirect();

    expect(Tag::withoutGlobalScopes()->find($tag->id))->toBeNull()
        ->and($contact->fresh()->tags)->toBeEmpty();
});

test('tag names are unique per account', function () {
    [$admin, $account] = memberWithRole('admin');
    Tag::factory()->for($account)->create(['name' => 'VIP']);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.tags.store'), [
            'name' => 'VIP',
            'color' => '#3b82f6',
        ])
        ->assertSessionHasErrors('name');
});
