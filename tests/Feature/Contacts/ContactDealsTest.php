<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reading contact deals returns only the deals of that contact', function () {
    [$member, $account] = memberWithRole('member');
    $pipeline = Pipeline::factory()->for($account)->create();
    $stage = PipelineStage::factory()->for($pipeline)->create(['name' => 'Calificado']);
    $contact = Contact::factory()->for($account)->create();
    $otherContact = Contact::factory()->for($account)->create();

    $deal = Deal::factory()->for($account)->forStage($stage)->create([
        'contact_id' => $contact->id,
        'title' => 'Implementación CRM',
    ]);
    Deal::factory()->for($account)->forStage($stage)->create(['contact_id' => $otherContact->id]);

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.deals', $contact));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('contacts')
        ->has('contactDeals', 1)
        ->where('contactDeals.0.id', $deal->id)
        ->where('contactDeals.0.title', 'Implementación CRM')
        ->where('contactDeals.0.stage.name', 'Calificado'));
});

test('contact deals are isolated per tenant', function () {
    [$member, $account] = memberWithRole('member');
    $foreignAccount = Account::factory()->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('contacts.deals', $foreignContact))
        ->assertNotFound();
});
