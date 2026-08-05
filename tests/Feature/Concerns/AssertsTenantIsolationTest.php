<?php

use App\Models\Scopes\AccountScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\AssertsTenantIsolation;
use Tests\Fixtures\TenantScopedFixture;
use Tests\Fixtures\TenantScopedFixtureFactory;

uses(AssertsTenantIsolation::class);
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

it('produces the same assertions as the manual tenant scope test', function () {
    $this->assertTenantIsolation(TenantScopedFixture::class, TenantScopedFixtureFactory::new());
});
