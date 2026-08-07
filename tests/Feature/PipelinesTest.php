<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('pipelines page returns the current account pipelines with real deals and contacts', function () {
    [$user, $account] = memberWithRole('admin');
    $foreignAccount = Account::factory()->create();

    $pipeline = Pipeline::factory()->for($account)->create(['name' => 'Ventas']);
    $stage = PipelineStage::factory()->for($pipeline)->create(['name' => 'Calificado']);
    $contact = Contact::factory()->for($account)->create([
        'name' => 'Ana Pérez',
        'phone' => '+57 300 000 0000',
    ]);
    Deal::factory()->for($account)->forStage($stage)->create([
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'title' => 'Implementación CRM',
        'value' => 125000,
    ]);

    $foreignPipeline = Pipeline::factory()->for($foreignAccount)->create();
    $foreignStage = PipelineStage::factory()->for($foreignPipeline)->create();
    Deal::factory()->for($foreignAccount)->forStage($foreignStage)->create();

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('pipelines'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('pipelines')
        ->has('pipelines', 1)
        ->where('pipelines.0.name', 'Ventas')
        ->has('pipelines.0.stages', 1)
        ->where('pipelines.0.stages.0.name', 'Calificado')
        ->has('pipelines.0.stages.0.deals', 1)
        ->where('pipelines.0.stages.0.deals.0.title', 'Implementación CRM')
        ->where('pipelines.0.stages.0.deals.0.contact.name', 'Ana Pérez')
        ->where('pipelines.0.stages.0.deals.0.assignee.id', $user->id)
        ->has('contacts', 1)
        ->where('contacts.0.id', $contact->id));
});

test('pipelines page loads board relations without a query per deal', function () {
    [$user, $account] = memberWithRole('admin');
    $pipeline = Pipeline::factory()->for($account)->create();
    $stages = PipelineStage::factory()->count(2)->for($pipeline)->create();

    foreach ($stages as $stage) {
        $contact = Contact::factory()->for($account)->create();
        Deal::factory()->for($account)->forStage($stage)->create([
            'user_id' => $user->id,
            'contact_id' => $contact->id,
        ]);
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from ["`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['pipelines', 'pipeline_stages', 'deals', 'contacts', 'users'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('pipelines'))
        ->assertOk();

    expect($queries)->toHaveCount(6)
        ->and(array_count_values($queries))->toMatchArray([
            'pipelines' => 1,
            'pipeline_stages' => 1,
            'deals' => 1,
            'contacts' => 2,
            'users' => 1,
        ]);
});

test('a user can create a deal for a real contact in the current account', function () {
    [$user, $account] = memberWithRole('member');
    $pipeline = Pipeline::factory()->for($account)->create();
    $stage = PipelineStage::factory()->for($pipeline)->create();
    $contact = Contact::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('pipelines.deals.store', $pipeline), [
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'title' => 'Nuevo deal',
            'value' => '950.50',
            'currency' => 'USD',
            'status' => 'open',
        ])
        ->assertRedirect(route('pipelines'));

    $deal = Deal::withoutGlobalScopes()->where('pipeline_id', $pipeline->id)->sole();

    expect($deal->title)->toBe('Nuevo deal')
        ->and($deal->contact_id)->toBe($contact->id)
        ->and($deal->assigned_to)->toBe($user->id)
        ->and((float) $deal->value)->toBe(950.5);
});

test('a deal can be updated and deleted without crossing accounts', function () {
    [$user, $account] = memberWithRole('member');
    $pipeline = Pipeline::factory()->for($account)->create();
    $stage = PipelineStage::factory()->for($pipeline)->create();
    $newStage = PipelineStage::factory()->for($pipeline)->create();
    $deal = Deal::factory()->for($account)->forStage($stage)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('pipelines.deals.update', $deal), [
            'stage_id' => $newStage->id,
            'title' => 'Deal actualizado',
        ])
        ->assertRedirect(route('pipelines'));

    expect($deal->fresh()->stage_id)->toBe($newStage->id)
        ->and($deal->fresh()->title)->toBe('Deal actualizado');

    $foreignAccount = Account::factory()->create();
    $foreignPipeline = Pipeline::factory()->for($foreignAccount)->create();
    $foreignStage = PipelineStage::factory()->for($foreignPipeline)->create();
    $foreignDeal = Deal::factory()->for($foreignAccount)->forStage($foreignStage)->create();
    $foreignContact = Contact::factory()->for($foreignAccount)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('pipelines.deals.store', $pipeline), [
            'stage_id' => $stage->id,
            'contact_id' => $foreignContact->id,
            'title' => 'No debe cruzar cuentas',
            'value' => 1,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('pipelines.deals.update', $deal), [
            'stage_id' => $foreignStage->id,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('pipelines.deals.destroy', $foreignDeal))
        ->assertNotFound();

    expect(Deal::withoutGlobalScopes()->find($foreignDeal->id))->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('pipelines.deals.destroy', $deal))
        ->assertRedirect(route('pipelines'));

    expect(Deal::withoutGlobalScopes()->find($deal->id))->toBeNull();
});
