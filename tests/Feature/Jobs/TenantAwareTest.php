<?php

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantAwareJobFixture;
use Tests\Fixtures\TenantScopedFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('tenant_scoped_fixtures', function (Blueprint $table) {
        $table->id();
        $table->uuid('account_id')->nullable();
        $table->string('name');
        $table->timestamps();
    });

    TenantAwareJobFixture::$boundAccountId = null;
    TenantAwareJobFixture::$visibleRowCount = null;
});

afterEach(function () {
    Schema::dropIfExists('tenant_scoped_fixtures');
    app()->forgetInstance(AccountScope::CONTAINER_KEY);

});

it('binds the serialized account before handle runs', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
    TenantScopedFixture::create(['name' => 'B-row']);
    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    TenantAwareJobFixture::dispatch($accountA->id, 'A-row');

    expect(TenantAwareJobFixture::$boundAccountId)->toBe($accountA->id);
    expect(TenantAwareJobFixture::$visibleRowCount)->toBe(1);

    $row = TenantScopedFixture::withoutGlobalScope(AccountScope::class)
        ->where('name', 'A-row')
        ->firstOrFail();

    expect($row->account_id)->toBe($accountA->id);
});
