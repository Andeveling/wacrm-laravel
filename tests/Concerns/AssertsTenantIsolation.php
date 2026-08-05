<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Runs the five tenant-isolation assertions `TenantScopeTest` (#12) hand-rolled
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

        expect($row->fresh()->account_id)->toBe($account->id);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertAccountIdIsNotOverwrittenWhenSet(Factory $factory): void
    {
        $account = Account::factory()->create();
        $otherAccount = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);

        $row = $factory->create(['account_id' => $otherAccount->id]);

        expect($row->fresh()->account_id)->toBe($otherAccount->id);

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

        expect($modelClass::count())->toBe(1);
        expect($modelClass::first()->getKey())->toBe($rowB->getKey());

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

        expect($bypassedCount)->toBe(2);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);
    }

    private function assertQueryReturnsNothingWithoutBoundAccount(string $modelClass, Factory $factory): void
    {
        $account = Account::factory()->create();
        app()->instance(AccountScope::CONTAINER_KEY, $account->id);
        $factory->create(['account_id' => $account->id]);

        app()->forgetInstance(AccountScope::CONTAINER_KEY);

        expect($modelClass::count())->toBe(0);
    }
}
