<?php

use App\Models\Account;
use App\Models\Flow;
use App\Models\FlowNode;
use App\Models\FlowRun;
use App\Models\FlowRunEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('flows page returns current account flows with real node counts', function () {
    [$user, $account] = memberWithRole('admin');
    $flow = Flow::factory()->for($account)->create(['name' => 'Bienvenida']);
    FlowNode::factory()->count(2)->for($flow)->create();
    Flow::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows')
            ->has('flows', 1)
            ->where('flows.0.id', $flow->id)
            ->where('flows.0.name', 'Bienvenida')
            ->where('flows.0.nodes_count', 2));
});

test('flows page eager loads node counts without an N+1 query', function () {
    [$user, $account] = memberWithRole('admin');
    $flows = Flow::factory()->count(2)->for($account)->create();

    foreach ($flows as $flow) {
        FlowNode::factory()->count(2)->for($flow)->create();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'flows')) {
            $queries[] = 'flows';
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows'))
        ->assertOk();

    expect(count($queries))->toBe(1);
});

test('flow editor returns real nodes and hides another account flow', function () {
    [$user, $account] = memberWithRole('admin');
    $flow = Flow::factory()->for($account)->create(['name' => 'Editor']);
    FlowNode::factory()->for($flow)->create(['node_key' => 'start']);
    $foreignFlow = Flow::factory()->for(Account::factory())->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows.show', $flow->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/editor')
            ->where('flow.id', $flow->id)
            ->where('flow.name', 'Editor')
            ->has('flow.nodes', 1)
            ->where('flow.nodes.0.node_key', 'start'));

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows.show', $foreignFlow->id))
        ->assertNotFound();
});

test('flow runs page returns runs and events without N+1 queries', function () {
    [$user, $account] = memberWithRole('admin');
    $flow = Flow::factory()->for($account)->create(['name' => 'Historial']);
    $run = FlowRun::factory()->for($flow)->create(['account_id' => $account->id]);
    FlowRunEvent::factory()->for($run, 'run')->started()->create();
    $foreignFlow = Flow::factory()->for(Account::factory())->create();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        foreach (['flows', 'flow_runs', 'flow_run_events'] as $table) {
            if (str_contains($query->sql, $table)) {
                $queries[] = $table;
            }
        }
    });

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows.runs', $flow->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/runs')
            ->where('flow.id', $flow->id)
            ->has('runs', 1)
            ->where('runs.0.id', $run->id)
            ->has('events', 1));

    expect(array_count_values($queries))->toMatchArray([
        'flows' => 1,
        'flow_runs' => 1,
        'flow_run_events' => 1,
    ]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows.runs', $foreignFlow->id))
        ->assertNotFound();
});

test('flow runs page returns an empty run list', function () {
    [$user, $account] = memberWithRole('admin');
    $flow = Flow::factory()->for($account)->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('flows.runs', $flow->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('flows/runs')
            ->has('runs', 0)
            ->has('events', 0));
});
