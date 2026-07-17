<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MultiAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_page_lists_only_the_users_accounts(): void
    {
        $user = User::factory()->create();
        $myAccount = Account::factory()->create(['name' => 'Mine']);
        $myAccount->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

        $otherUsersAccount = Account::factory()->create(['name' => 'Not mine']);
        $otherUsersAccount->users()->attach(User::factory()->create()->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($user)
            ->get(route('accounts.switch'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('accounts/switch')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Mine')
            );
    }

    public function test_user_can_switch_to_an_account_they_belong_to(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $account->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($account->id, session('current_account_id'));
    }

    public function test_user_cannot_switch_to_an_account_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();

        $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

        $response->assertForbidden();
        $this->assertNull(session('current_account_id'));
    }
}
