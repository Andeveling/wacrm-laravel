<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembersListTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_the_members_page(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Co']);
        $viewer = User::factory()->create(['name' => 'Vera Viewer', 'email' => 'vera@example.com']);
        $account->users()->attach($viewer->id, ['role' => AccountRole::Viewer->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($viewer)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Members')
            ->where('account.id', $account->id)
            ->where('account.name', 'Acme Co')
            ->where('account.role', AccountRole::Viewer->value)
            ->where('is_owner', false)
            ->where('is_admin', false)
            ->has('members', 1)
            ->where('members.0.id', $viewer->id)
            ->where('members.0.name', 'Vera Viewer')
            ->where('members.0.email', 'vera@example.com')
            ->where('members.0.role', AccountRole::Viewer->value)
            ->where('members.0.is_you', true)
        );
    }

    public function test_owner_sees_is_owner_true(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Members')
            ->where('is_owner', true)
            ->where('is_admin', true)
            ->where('account.role', AccountRole::Owner->value)
        );
    }

    public function test_admin_sees_is_admin_true_but_not_owner(): void
    {
        $account = Account::factory()->create();
        $admin = User::factory()->create();
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Members')
            ->where('is_owner', false)
            ->where('is_admin', true)
            ->where('account.role', AccountRole::Admin->value)
        );
    }

    public function test_non_member_gets_403(): void
    {
        $account = Account::factory()->create();
        $outsider = User::factory()->create();

        $response = $this
            ->actingAs($outsider)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $account = Account::factory()->create();

        $response = $this->get(route('accounts.members.index', $account));

        $response->assertRedirect(route('login'));
    }

    public function test_members_array_contains_all_account_members_with_joined_at_iso_string(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create(['name' => 'Owen Owner']);
        $member = User::factory()->create(['name' => 'Mike Member']);
        $joinedAt = now()->subDays(7);
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => $joinedAt]);
        $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => $joinedAt]);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Accounts/Members')
            ->has('members', 2)
            ->where('members.0.joined_at', $joinedAt->toIso8601String())
        );
    }
}
