<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantScopedFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('tenant_scoped_fixtures', function (Blueprint $table) {
        $table->id();
        $table->uuid('account_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('tenant_scoped_fixtures');
    app()->forgetInstance(AccountScope::CONTAINER_KEY);

});

test('account id autopopulates on creating when current account is bound', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $fixture = TenantScopedFixture::create(['name' => 'Widget']);

    expect($fixture->account_id)->toBe($account->id);
});

test('account id is not overwritten when already set', function () {
    $account = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $fixture = TenantScopedFixture::create(['name' => 'Widget', 'account_id' => $otherAccount->id]);

    expect($fixture->account_id)->toBe($otherAccount->id);
});

test('query is filtered to the current account', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
    TenantScopedFixture::create(['name' => 'A-row']);

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    TenantScopedFixture::create(['name' => 'B-row']);

    expect(TenantScopedFixture::count())->toBe(1);
    expect(TenantScopedFixture::first()->name)->toBe('B-row');
});

test('without global scope bypasses the tenant filter', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
    TenantScopedFixture::create(['name' => 'A-row']);

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    TenantScopedFixture::create(['name' => 'B-row']);

    expect(TenantScopedFixture::withoutGlobalScope(AccountScope::class)->count())->toBe(2);
});

test('query returns nothing when no account is bound', function () {
    $account = Account::factory()->create();
    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    TenantScopedFixture::create(['name' => 'A-row']);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    expect(TenantScopedFixture::count())->toBe(0);
});

test('account users relation exposes role and joined at', function () {
    $account = Account::factory()->create(['type' => AccountType::Team]);
    $user = User::factory()->create();
    $joinedAt = now();

    $account->users()->attach($user->id, [
        'role' => 'owner',
        'joined_at' => $joinedAt,
    ]);

    $pivot = $account->users()->first()->pivot;

    expect($pivot)->toBeInstanceOf(AccountUser::class);
    expect($pivot->role)->toBe(AccountRole::Owner);
    expect($pivot->joined_at->toDateTimeString())->toBe($joinedAt->toDateTimeString());
});
