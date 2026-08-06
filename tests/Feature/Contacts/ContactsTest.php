<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\CustomField;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('contacts index is tenant scoped and includes tags and custom fields', function () {
    [$owner, $account] = memberWithRole('owner');
    $foreignAccount = Account::factory()->create();
    $tag = Tag::factory()->for($account)->create(['name' => 'VIP']);
    CustomField::factory()->for($account)->create(['field_name' => 'Fuente']);
    $contact = Contact::factory()->for($account)->create(['user_id' => $owner->id]);
    Contact::factory()->for($foreignAccount)->create();
    ContactTag::create(['contact_id' => $contact->id, 'tag_id' => $tag->id]);

    $response = $this->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('contacts')
        ->has('contacts', 1)
        ->where('contacts.0.id', $contact->id)
        ->where('contacts.0.tags.0.id', $tag->id)
        ->has('tags', 1)
        ->has('customFields', 1));
});

test('account member can create update and delete a contact without crossing tenants', function () {
    [$member, $account] = memberWithRole('member');
    $tag = Tag::factory()->for($account)->create();

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.store'), [
            'phone' => '+57 300 123 4567',
            'name' => 'Laura Gómez',
            'email' => 'laura@example.com',
            'tag_ids' => [$tag->id],
        ]);

    $response->assertRedirect(route('contacts'));
    $contact = Contact::withoutGlobalScopes()->where('account_id', $account->id)->sole();
    expect($contact->name)->toBe('Laura Gómez')
        ->and($contact->tags()->count())->toBe(1);

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('contacts.update', $contact->id), [
            'phone' => $contact->phone,
            'name' => 'Laura Actualizada',
            'email' => null,
            'company' => 'Acme',
            'tag_ids' => [],
        ])
        ->assertRedirect(route('contacts'));

    expect($contact->fresh()->name)->toBe('Laura Actualizada')
        ->and($contact->fresh()->tags()->count())->toBe(0);

    $foreignAccount = Account::factory()->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.destroy', $foreignContact->id))
        ->assertNotFound();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.destroy', $contact->id))
        ->assertRedirect(route('contacts'));

    expect(Contact::withoutGlobalScopes()->find($contact->id))->toBeNull();
    expect(Contact::withoutGlobalScopes()->find($foreignContact->id))->not->toBeNull();
});

test('contacts reject duplicate normalized phones within the account', function () {
    [$owner, $account] = memberWithRole('owner');
    Contact::factory()->for($account)->create(['phone' => '+57 300 123 4567']);

    $this->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.store'), ['phone' => '573001234567'])
        ->assertSessionHasErrors('phone');
});

test('admins manage custom fields while members cannot', function () {
    [$admin, $account] = memberWithRole('admin');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.custom-fields.store'), [
            'field_name' => 'Fuente',
            'field_type' => 'text',
        ])
        ->assertRedirect(route('contacts'));

    $field = CustomField::withoutGlobalScopes()->where('account_id', $account->id)->sole();

    [$member, $memberAccount] = memberWithRole('member');
    $this->actingAs($member)
        ->withSession(['current_account_id' => $memberAccount->id])
        ->post(route('contacts.custom-fields.store'), [
            'field_name' => 'No permitido',
            'field_type' => 'text',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('contacts.custom-fields.destroy', $field->id))
        ->assertRedirect(route('contacts'));

    expect(CustomField::withoutGlobalScopes()->find($field->id))->toBeNull();
});

test('contacts import and export round trip csv data', function () {
    [$owner, $account] = memberWithRole('owner');
    $csv = UploadedFile::fake()->createWithContent(
        'contacts.csv',
        "phone,name,email,company,tags\n+57 300 999 0000,Ana Pérez,ana@example.com,Acme,VIP; Cliente\n",
    );

    $this->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('contacts.import'), ['file' => $csv])
        ->assertRedirect(route('contacts'));

    $contact = Contact::withoutGlobalScopes()->where('account_id', $account->id)->sole();
    expect($contact->name)->toBe('Ana Pérez')
        ->and($contact->tags()->count())->toBe(2);

    $response = $this->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.export'));

    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=contacts.csv');

    expect($response->streamedContent())->toContain('Ana Pérez');
});
