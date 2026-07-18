<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAccountMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_binds_current_account_from_session(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertTrue(app()->bound(CurrentAccount::class));
        $this->assertSame($account->id, app(CurrentAccount::class)->id());
        $this->assertSame(AccountRole::Owner, app(CurrentAccount::class)->role());
    }

    public function test_it_returns_403_when_session_account_no_longer_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $otherAccount = Account::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $otherAccount->id])
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_it_redirects_to_switcher_when_no_current_account_is_selected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('accounts.switch'));
    }
}
