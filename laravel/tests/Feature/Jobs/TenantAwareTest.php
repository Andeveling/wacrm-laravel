<?php

namespace Tests\Feature\Jobs;

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\TenantAwareJobFixture;
use Tests\Fixtures\TenantScopedFixture;
use Tests\TestCase;

class TenantAwareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_scoped_fixtures', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        TenantAwareJobFixture::$boundAccountId = null;
        TenantAwareJobFixture::$visibleRowCount = null;
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_scoped_fixtures');
        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        parent::tearDown();
    }

    #[Test]
    public function it_binds_the_serialized_account_before_handle_runs(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        TenantScopedFixture::create(['name' => 'B-row']);
        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        TenantAwareJobFixture::dispatch($accountA->id, 'A-row');

        $this->assertSame($accountA->id, TenantAwareJobFixture::$boundAccountId);
        $this->assertSame(1, TenantAwareJobFixture::$visibleRowCount);

        $row = TenantScopedFixture::withoutGlobalScope(AccountScope::class)
            ->where('name', 'A-row')
            ->firstOrFail();

        $this->assertSame($accountA->id, $row->account_id);
    }
}
