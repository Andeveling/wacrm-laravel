<?php

use App\Models\Account;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\MessageTemplateStatus;
use App\Models\MessageTemplate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('broadcasts page returns current account broadcasts with real metrics', function () {
    [$user, $account] = memberWithRole('admin');
    $broadcast = Broadcast::factory()->for($account)->sent()->create([
        'name' => 'Campaña de agosto',
        'template_name' => 'promo_agosto',
        'status' => BroadcastStatus::Sent,
    ]);

    Broadcast::factory()->for(Account::factory())->create(['name' => 'Privada']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts')
            ->has('broadcasts', 1)
            ->where('broadcasts.0.id', $broadcast->id)
            ->where('broadcasts.0.name', 'Campaña de agosto')
            ->where('broadcasts.0.status', 'sent')
            ->where('broadcasts.0.template_name', 'promo_agosto')
            ->where('broadcasts.0.total_recipients', 5)
            ->where('broadcasts.0.created_at', $broadcast->created_at?->toISOString()));
});

test('broadcast detail returns recipients and isolates another account', function () {
    [$user, $account] = memberWithRole('admin');
    $contact = Contact::factory()->for($account)->create([
        'name' => 'Ana Pérez',
        'phone' => '+573001112233',
    ]);
    $broadcast = Broadcast::factory()->for($account)->create(['name' => 'Seguimiento']);
    $recipient = BroadcastRecipient::factory()
        ->for($broadcast)
        ->for($contact)
        ->delivered()
        ->create();
    $foreignBroadcast = Broadcast::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $broadcast->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts/show')
            ->where('broadcast.id', $broadcast->id)
            ->where('broadcast.name', 'Seguimiento')
            ->has('recipients', 1)
            ->where('recipients.0.id', $recipient->id)
            ->where('recipients.0.contact.name', 'Ana Pérez')
            ->where('recipients.0.status', 'delivered'));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $foreignBroadcast->id))
        ->assertNotFound();
});

test('broadcast detail eager loads recipients and contacts', function () {
    [$user, $account] = memberWithRole('admin');
    $broadcast = Broadcast::factory()->for($account)->create();
    $contacts = Contact::factory()->count(2)->for($account)->create();

    foreach ($contacts as $contact) {
        BroadcastRecipient::factory()->for($broadcast)->for($contact)->create();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/from [\"`]?([a-z_]+)/i', $query->sql, $matches) === 1
            && in_array($matches[1], ['broadcasts', 'broadcast_recipients', 'contacts'], true)) {
            $queries[] = $matches[1];
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts.show', $broadcast->id))
        ->assertOk();

    expect(array_count_values($queries))->toMatchArray([
        'broadcasts' => 1,
        'broadcast_recipients' => 1,
        'contacts' => 1,
    ]);
});

test('message templates page returns current account templates and approval status', function () {
    [$user, $account] = memberWithRole('admin');
    $template = MessageTemplate::factory()->for($account)->create([
        'name' => 'promo_agosto',
        'status' => MessageTemplateStatus::Approved,
        'rejection_reason' => null,
    ]);

    MessageTemplate::factory()->for(Account::factory())->create(['name' => 'privada']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/templates')
            ->has('templates', 1)
            ->where('templates.0.id', $template->id)
            ->where('templates.0.name', 'promo_agosto')
            ->where('templates.0.status', 'APPROVED'));
});

test('broadcast and template pages return empty collections', function () {
    [$user, $account] = memberWithRole('admin');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('broadcasts'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('broadcasts')
            ->has('broadcasts', 0));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/templates')
            ->has('templates', 0));
});
