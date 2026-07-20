<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountUser>
 */
class AccountUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Note: account_user has a composite PK (account_id, user_id) and no
     * timestamp columns, so the factory never sets them.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'role' => AccountRole::Member,
            'joined_at' => now(),
        ];
    }

    /**
     * Pin the membership to a specific account.
     */
    public function forAccount(Account $account): static
    {
        return $this->state(fn (): array => ['account_id' => $account->id]);
    }

    /**
     * Pin the membership to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    /**
     * Set the membership role.
     */
    public function asRole(AccountRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
