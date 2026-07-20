<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembersUITest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_gets_member_management_ui_contract_with_a_locked_sole_owner_row(): void
    {
        [$account, $owner, $member] = $this->accountViewedBy(AccountRole::Owner);

        $this->getMembersPage($owner, $account)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/members')
                ->where('is_owner', true)
                ->where('is_admin', true)
                ->has('members', 2)
                ->where('members.0.id', $member->id)
                ->where('members.1.id', $owner->id)
                ->where('members.1.role', AccountRole::Owner->value)
                ->where('members.1.is_you', true)
            );
    }

    public function test_admin_gets_member_management_ui_contract_without_owner_privileges(): void
    {
        [$account, $admin] = $this->accountViewedBy(AccountRole::Admin);

        $this->getMembersPage($admin, $account)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/members')
                ->where('is_owner', false)
                ->where('is_admin', true)
            );
    }

    public function test_member_gets_read_only_ui_contract(): void
    {
        [$account, $member] = $this->accountViewedBy(AccountRole::Member);

        $this->getMembersPage($member, $account)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/members')
                ->where('is_owner', false)
                ->where('is_admin', false)
            );
    }

    public function test_viewer_gets_read_only_ui_contract(): void
    {
        [$account, $viewer] = $this->accountViewedBy(AccountRole::Viewer);

        $this->getMembersPage($viewer, $account)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('accounts/members')
                ->where('is_owner', false)
                ->where('is_admin', false)
            );
    }

    /**
     * @return array{Account, User, User}
     */
    private function accountViewedBy(AccountRole $role): array
    {
        $account = Account::factory()->create(['name' => 'Acme Co']);
        $viewer = User::factory()->create(['name' => 'Zoe Viewer']);
        $member = User::factory()->create(['name' => 'Alice Member']);

        $account->users()->attach($viewer->id, ['role' => $role->value, 'joined_at' => now()]);
        $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

        return [$account, $viewer, $member];
    }

    private function getMembersPage(User $viewer, Account $account): TestResponse
    {
        return $this
            ->actingAs($viewer)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('accounts.members.index', $account));
    }
}
