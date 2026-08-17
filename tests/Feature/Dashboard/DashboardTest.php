<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Auth / routing
// ---------------------------------------------------------------------------

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users without a membership see the empty chrome', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/no-account')
            ->where('currentAccount', null)
            ->where('accounts', [])
        );
});

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

test('dashboard receives all expected Inertia props', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('metrics')
            ->has('conversationsSeries')
            ->has('pipeline')
            ->has('responseTime')
            ->has('activity'),
        );
});

// ---------------------------------------------------------------------------
// Metrics
// ---------------------------------------------------------------------------

test('metrics shows zeros for a fresh account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('metrics.activeConversations.current', 0)
            ->where('metrics.activeConversations.previous', 0)
            ->where('metrics.newContactsToday.current', 0)
            ->where('metrics.newContactsToday.previous', 0)
            ->where('metrics.openDealsValue', 0)
            ->where('metrics.openDealsCount', 0)
            ->where('metrics.messagesSentToday.current', 0)
            ->where('metrics.messagesSentToday.previous', 0),
        );
});

test('metrics counts real active conversations', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    Conversation::factory()->for($account)->create(['status' => 'open']);
    Conversation::factory()->for($account)->create(['status' => 'pending']);
    Conversation::factory()->for($account)->create(['status' => 'closed']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('metrics.activeConversations.current', 2),
        );
});

test('metrics counts real open deals value', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $pipeline = Pipeline::factory()->for($account)->create();
    $stage = PipelineStage::factory()->for($pipeline)->create();

    Deal::factory()->for($account)->forStage($stage)->create(['value' => 1500, 'status' => 'open']);
    Deal::factory()->for($account)->forStage($stage)->create(['value' => 500, 'status' => 'won']);
    Deal::factory()->for($account)->forStage($stage)->create(['value' => 2000, 'status' => 'open']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('metrics.openDealsValue', 3500)
            ->where('metrics.openDealsCount', 2),
        );
});

// ---------------------------------------------------------------------------
// Tenant isolation
// ---------------------------------------------------------------------------

test('dashboard never leaks data from another account', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $accountA->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $accountB->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $pipelineA = Pipeline::factory()->for($accountA)->create();
    $stageA = PipelineStage::factory()->for($pipelineA)->create();

    Deal::factory()->for($accountA)->forStage($stageA)->create(['value' => 5000, 'status' => 'open']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountB->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('metrics.openDealsValue', 0)
            ->where('metrics.openDealsCount', 0),
        );
});

test('dashboard activity feed only shows current account events', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $accountA->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $accountB->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    Contact::factory()->for($accountA)->create(['name' => 'Alice']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $accountB->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity', []),
        );
});

// ---------------------------------------------------------------------------
// Conversations series
// ---------------------------------------------------------------------------

test('conversations series returns daily buckets', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $conversation = Conversation::factory()->for($account)->create();
    $today = now()->format('Y-m-d');

    Message::factory()->for($conversation)->incoming()->create(['created_at' => now()->setTime(10, 0)]);
    Message::factory()->for($conversation)->outgoing()->create(['created_at' => now()->setTime(10, 5)]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('conversationsSeries.7.6.day', $today)
            ->where('conversationsSeries.7.6.incoming', 1)
            ->where('conversationsSeries.7.6.outgoing', 1),
        );
});

// ---------------------------------------------------------------------------
// Pipeline
// ---------------------------------------------------------------------------

test('pipeline donut groups open deals by stage', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $pipeline = Pipeline::factory()->for($account)->create();
    $stage1 = PipelineStage::factory()->for($pipeline)->create(['name' => 'Lead', 'color' => '#0000ff']);
    $stage2 = PipelineStage::factory()->for($pipeline)->create(['name' => 'Negociación', 'color' => '#ff0000']);

    Deal::factory()->for($account)->forStage($stage1)->create(['value' => 1000, 'status' => 'open']);
    Deal::factory()->for($account)->forStage($stage1)->create(['value' => 500, 'status' => 'open']);
    Deal::factory()->for($account)->forStage($stage2)->create(['value' => 2000, 'status' => 'open']);
    Deal::factory()->for($account)->forStage($stage1)->create(['value' => 100, 'status' => 'won']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pipeline.totalValue', 3500)
            ->has('pipeline.stages', 2),
        );
});

// ---------------------------------------------------------------------------
// Activity
// ---------------------------------------------------------------------------

test('activity feed includes recent contacts', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    Contact::factory()->for($account)->create(['name' => 'Carlos']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('activity', 1)
            ->where('activity.0.kind', 'contact')
            ->where('activity.0.text', 'Nuevo contacto: Carlos'),
        );
});

test('activity feed includes recent deals', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $pipeline = Pipeline::factory()->for($account)->create();
    $stage = PipelineStage::factory()->for($pipeline)->create(['name' => 'Lead']);

    Deal::factory()->for($account)->forStage($stage)->create(['title' => 'Gran proyecto']);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity.0.kind', 'deal')
            ->where('activity.0.text', 'Negocio "Gran proyecto" en "Lead"'),
        );
});

// ---------------------------------------------------------------------------
// Response time
// ---------------------------------------------------------------------------

test('response time shows null averages for a fresh account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('responseTime.thisWeekAvg', null)
            ->where('responseTime.lastWeekAvg', null),
        );
});
