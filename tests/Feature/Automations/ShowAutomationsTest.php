<?php

use App\Models\Account;
use App\Models\Automation;
use App\Models\AutomationLog;
use App\Models\AutomationStep;
use App\Models\Contact;
use App\Models\Enums\AutomationConnectionMode;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

test('automations page returns the current account automations with their steps', function () {
    [$user, $account] = memberWithRole('admin');
    $automation = Automation::factory()->for($account)->create([
        'name' => 'Bienvenida',
        'trigger_type' => 'first_inbound_message',
        'is_active' => true,
        'execution_count' => 12,
    ]);
    AutomationStep::factory()->for($automation)->create(['step_type' => 'send_message', 'position' => 0]);
    AutomationStep::factory()->for($automation)->create(['step_type' => 'add_tag', 'position' => 1]);

    $foreignAccount = Account::factory()->create();
    Automation::factory()->for($foreignAccount)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations')
            ->has('automations', 1)
            ->where('automations.0.id', $automation->id)
            ->where('automations.0.name', 'Bienvenida')
            ->where('automations.0.trigger_type', 'first_inbound_message')
            ->where('automations.0.is_active', true)
            ->has('automations.0.steps', 2)
            ->where('automations.0.steps.0.step_type', 'send_message'));
});

test('automations page returns an empty list for an account without automations', function () {
    [$user, $account] = memberWithRole('admin');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations')
            ->has('automations', 0));
});

test('automations page eager loads steps', function () {
    [$user, $account] = memberWithRole('admin');
    $automations = Automation::factory()->count(2)->for($account)->create();

    foreach ($automations as $automation) {
        AutomationStep::factory()->count(2)->for($automation)->create();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from ["`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['automations', 'automation_steps'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations'))
        ->assertOk();

    expect(array_count_values($queries))->toMatchArray([
        'automations' => 1,
        'automation_steps' => 1,
    ]);
});

test('automation edit page loads the requested current account automation', function () {
    [$user, $account] = memberWithRole('admin');
    $automation = Automation::factory()->for($account)->create(['name' => 'Seguimiento']);
    AutomationStep::factory()->for($automation)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations.edit', $automation->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations/edit')
            ->where('automation.id', $automation->id)
            ->where('automation.name', 'Seguimiento')
            ->has('automation.steps', 1));
});

test('automation edit and logs pages do not expose another account automation', function () {
    [$user, $account] = memberWithRole('admin');
    $foreignAutomation = Automation::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations.edit', $foreignAutomation->id))
        ->assertNotFound();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations.logs', $foreignAutomation->id))
        ->assertNotFound();
});

test('automation logs page returns real executions ordered by date', function () {
    [$user, $account] = memberWithRole('admin');
    $automation = Automation::factory()->for($account)->create();
    $contact = Contact::factory()->for($account)->create(['name' => 'Ana Pérez']);
    $oldLog = AutomationLog::factory()->for($automation)->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'created_at' => now()->subDay(),
    ]);
    $newLog = AutomationLog::factory()->for($automation)->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'contact_id' => $contact->id,
        'created_at' => now(),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from ["`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['automations', 'automation_logs', 'contacts'], true)) {
            $queries[] = $matches[1];
        }
    });

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('automations.logs', $automation->id));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations/logs')
            ->where('automation.id', $automation->id)
            ->has('logs', 2)
            ->where('logs.0.id', $newLog->id)
            ->where('logs.0.contact.name', 'Ana Pérez')
            ->where('logs.1.id', $oldLog->id));

    expect(array_count_values($queries))->toMatchArray([
        'automations' => 1,
        'automation_logs' => 1,
        'contacts' => 1,
    ]);
});

test('an outbound automation pins an active connection', function () {
    [$user, $account] = memberWithRole('admin');
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create(['is_default' => true]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('automations.store'), [
            'name' => 'Recordatorio',
            'trigger_type' => 'time_based',
            'connection_mode' => 'pinned',
            'connection_id' => $sales->id,
        ])
        ->assertRedirect();

    $automation = Automation::query()->sole();

    expect($automation->name)->toBe('Recordatorio')
        ->and($automation->trigger_type)->toBe('time_based')
        ->and($automation->connection_mode)->toBe(AutomationConnectionMode::Pinned)
        ->and($automation->connection_id)->toBe($sales->id);
});

test('an inbound automation can use the triggering connection', function () {
    [$user, $account] = memberWithRole('admin');
    WhatsappPhoneNumberConnection::factory()->for($account)->active()->create(['is_default' => true]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('automations.store'), [
            'name' => 'Bienvenida',
            'trigger_type' => 'first_inbound_message',
            'connection_mode' => 'trigger',
        ])
        ->assertRedirect();

    $automation = Automation::query()->sole();

    expect($automation->connection_mode)->toBe(AutomationConnectionMode::Trigger)
        ->and($automation->connection_id)->toBeNull();
});

test('an outbound automation cannot use the triggering connection', function () {
    [$user, $account] = memberWithRole('admin');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->from(route('automations.new'))
        ->post(route('automations.store'), [
            'name' => 'Recordatorio',
            'trigger_type' => 'time_based',
            'connection_mode' => 'trigger',
        ])
        ->assertRedirect(route('automations.new'))
        ->assertSessionHasErrors('connection_mode');

    expect(Automation::query()->count())->toBe(0);
});

test('disconnecting a pinned connection pauses the automation instead of switching sender', function () {
    [$user, $account] = memberWithRole('admin');
    $sales = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    $support = WhatsappPhoneNumberConnection::factory()->for($account)->active()->create();
    Http::fake();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('automations.store'), [
            'name' => 'Recordatorio',
            'trigger_type' => 'time_based',
            'connection_mode' => 'pinned',
            'connection_id' => $sales->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    $automation = Automation::query()->sole();

    $this->delete(route('settings.whatsapp.disconnect', $sales))
        ->assertRedirect(route('settings.whatsapp'));

    $automation = $automation->fresh();

    expect($automation)->not->toBeNull()
        ->and($automation->is_active)->toBeFalse()
        ->and($automation->connection_id)->toBe($sales->id)
        ->and($support->fresh()->readiness->value)->toBe('active');
});
