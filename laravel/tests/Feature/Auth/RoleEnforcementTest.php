<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user attached to a fresh Team account with the given role.
     *
     * @return array{0: User, 1: Account}
     */
    private function memberWithRole(string $role): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $account->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

        return [$user, $account];
    }

    public function test_owner_has_full_control_including_billing_and_deletion(): void
    {
        [$owner, $account] = $this->memberWithRole('owner');
        $gate = Gate::forUser($owner);

        $this->assertTrue($gate->allows('manageMembers', $account));
        $this->assertTrue($gate->allows('editSettings', $account));
        $this->assertTrue($gate->allows('manageBilling', $account));
        $this->assertTrue($gate->allows('delete', $account));
        $this->assertTrue($gate->allows('writeOperationalData', $account));
        $this->assertTrue($gate->allows('viewOperationalData', $account));
    }

    public function test_admin_manages_members_and_settings_but_not_billing_or_deletion(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $gate = Gate::forUser($admin);

        $this->assertTrue($gate->allows('manageMembers', $account));
        $this->assertTrue($gate->allows('editSettings', $account));
        $this->assertTrue($gate->allows('writeOperationalData', $account));
        $this->assertFalse($gate->allows('manageBilling', $account));
        $this->assertFalse($gate->allows('delete', $account));
    }

    public function test_member_writes_operational_data_but_cannot_manage_the_account(): void
    {
        [$member, $account] = $this->memberWithRole('member');
        $gate = Gate::forUser($member);

        $this->assertTrue($gate->allows('writeOperationalData', $account));
        $this->assertTrue($gate->allows('viewOperationalData', $account));
        $this->assertFalse($gate->allows('manageMembers', $account));
        $this->assertFalse($gate->allows('editSettings', $account));
        $this->assertFalse($gate->allows('manageBilling', $account));
        $this->assertFalse($gate->allows('delete', $account));
    }

    public function test_viewer_has_read_only_access(): void
    {
        [$viewer, $account] = $this->memberWithRole('viewer');
        $gate = Gate::forUser($viewer);

        $this->assertTrue($gate->allows('viewOperationalData', $account));
        $this->assertFalse($gate->allows('writeOperationalData', $account));
        $this->assertFalse($gate->allows('manageMembers', $account));
        $this->assertFalse($gate->allows('editSettings', $account));
        $this->assertFalse($gate->allows('manageBilling', $account));
        $this->assertFalse($gate->allows('delete', $account));
    }

    public function test_non_member_has_no_access_at_all(): void
    {
        $outsider = User::factory()->create();
        $account = Account::factory()->create();
        $gate = Gate::forUser($outsider);

        $this->assertFalse($gate->allows('viewOperationalData', $account));
        $this->assertFalse($gate->allows('writeOperationalData', $account));
        $this->assertFalse($gate->allows('manageMembers', $account));
        $this->assertFalse($gate->allows('editSettings', $account));
        $this->assertFalse($gate->allows('manageBilling', $account));
        $this->assertFalse($gate->allows('delete', $account));
    }
}
