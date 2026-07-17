<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountType;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantScopedFixture;
use Tests\TestCase;

class TenantScopeTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_scoped_fixtures');
        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        parent::tearDown();
    }

    public function test_account_id_autopopulates_on_creating_when_current_account_is_bound(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $fixture = TenantScopedFixture::create(['name' => 'Widget']);

        $this->assertSame($account->id, $fixture->account_id);
    }

    public function test_account_id_is_not_overwritten_when_already_set(): void
    {
        $account = Account::factory()->create();
        $otherAccount = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $fixture = TenantScopedFixture::create(['name' => 'Widget', 'account_id' => $otherAccount->id]);

        $this->assertSame($otherAccount->id, $fixture->account_id);
    }

    public function test_query_is_filtered_to_the_current_account(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
        TenantScopedFixture::create(['name' => 'A-row']);

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        TenantScopedFixture::create(['name' => 'B-row']);

        $this->assertSame(1, TenantScopedFixture::count());
        $this->assertSame('B-row', TenantScopedFixture::first()->name);
    }

    public function test_without_global_scope_bypasses_the_tenant_filter(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
        TenantScopedFixture::create(['name' => 'A-row']);

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        TenantScopedFixture::create(['name' => 'B-row']);

        $this->assertSame(2, TenantScopedFixture::withoutGlobalScope(AccountScope::class)->count());
    }

    public function test_query_returns_nothing_when_no_account_is_bound(): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);
        TenantScopedFixture::create(['name' => 'A-row']);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        $this->assertSame(0, TenantScopedFixture::count());
    }

    public function test_account_users_relation_exposes_role_and_joined_at(): void
    {
        $account = Account::factory()->create(['type' => AccountType::Team]);
        $user = User::factory()->create();
        $joinedAt = now();

        $account->users()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => $joinedAt,
        ]);

        $pivot = $account->users()->first()->pivot;

        $this->assertInstanceOf(AccountUser::class, $pivot);
        $this->assertSame('owner', $pivot->role);
        $this->assertSame($joinedAt->toDateTimeString(), $pivot->joined_at->toDateTimeString());
    }
}
