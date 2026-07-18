<?php

namespace Tests\Feature\Concerns;

use App\Models\Scopes\AccountScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsTenantIsolation;
use Tests\Fixtures\TenantScopedFixture;
use Tests\Fixtures\TenantScopedFixtureFactory;
use Tests\TestCase;

class AssertsTenantIsolationTest extends TestCase
{
    use AssertsTenantIsolation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_scoped_fixtures', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_scoped_fixtures');
        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        parent::tearDown();
    }

    #[Test]
    public function it_produces_the_same_assertions_as_the_manual_tenant_scope_test(): void
    {
        $this->assertTenantIsolation(TenantScopedFixture::class, TenantScopedFixtureFactory::new());
    }
}
