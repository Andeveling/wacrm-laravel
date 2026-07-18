<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Runs the six tenant-isolation assertions `TenantScopeTest` (#12) hand-rolled
 * against `TenantScopedFixture`, parametrized so any `BelongsToAccount` model
 * can reuse them: `$this->assertTenantIsolation(Contact::class, ContactFactory::new())`.
 */
trait AssertsTenantIsolation
{
    /**
     * @param  class-string  $modelClass  A model using the BelongsToAccount trait.
     */
    protected function assertTenantIsolation(string $modelClass, Factory $factory): void
    {
        $this->assertAccountIdAutopopulatesOnCreating($factory);
        $this->assertAccountIdIsNotOverwrittenWhenSet($factory);
        $this->assertQueryIsFilteredToCurrentAccount($modelClass, $factory);
        $this->assertWithoutGlobalScopeBypassesFilter($modelClass, $factory);
        $this->assertQueryReturnsNothingWithoutBoundAccount($modelClass, $factory);
    }

    private function assertAccountIdAutopopulatesOnCreating(Factory $factory): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $row = $factory->create(['account_id' => null]);

        $this->assertSame($account->id, $row->fresh()->account_id);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertAccountIdIsNotOverwrittenWhenSet(Factory $factory): void
    {
        $account = Account::factory()->create();
        $otherAccount = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $row = $factory->create(['account_id' => $otherAccount->id]);

        $this->assertSame($otherAccount->id, $row->fresh()->account_id);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertQueryIsFilteredToCurrentAccount(string $modelClass, Factory $factory): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
        $factory->create(['account_id' => $accountA->id]);

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        $rowB = $factory->create(['account_id' => $accountB->id]);

        $this->assertSame(1, $modelClass::count());
        $this->assertSame($rowB->getKey(), $modelClass::first()->getKey());

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertWithoutGlobalScopeBypassesFilter(string $modelClass, Factory $factory): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        app()->instance(AccountScope::CONTAINER_KEY, $accountA->id);
        $factory->create(['account_id' => $accountA->id]);

        app()->instance(AccountScope::CONTAINER_KEY, $accountB->id);
        $factory->create(['account_id' => $accountB->id]);

        $bypassedCount = $modelClass::withoutGlobalScope(AccountScope::class)
            ->whereIn('account_id', [$accountA->id, $accountB->id])
            ->count();

        $this->assertSame(2, $bypassedCount);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertQueryReturnsNothingWithoutBoundAccount(string $modelClass, Factory $factory): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);
        $factory->create(['account_id' => $account->id]);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        $this->assertSame(0, $modelClass::count());
    }
}
