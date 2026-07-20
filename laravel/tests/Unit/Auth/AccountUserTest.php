<?php

namespace Tests\Unit\Auth;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pins down the schema contract for the account_user pivot: it tracks
 * `role` and `joined_at` only, so Eloquent's managed `created_at` /
 * `updated_at` columns are off. Any code that inserts directly with
 * `AccountUser::create()` must round-trip without writing timestamps.
 */
class AccountUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function direct_create_round_trips_without_timestamps_columns(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();

        $pivot = AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => AccountRole::Owner,
            'joined_at' => now(),
        ]);

        $this->assertInstanceOf(AccountUser::class, $pivot);
        $this->assertDatabaseHas('account_user', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => AccountRole::Owner->value,
        ]);
        $this->assertDatabaseMissing('account_user', [
            'account_id' => $account->id,
            'user_id' => $user->id,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
