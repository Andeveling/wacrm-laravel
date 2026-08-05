<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can demote an admin to member', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $admin = attachUserToAccount($account, AccountRole::Admin);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $admin]), [
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHas('role_changed');

    expect(roleIn($account, $admin->id))->toBe(AccountRole::Member, 'Admin pivot role must be updated to Member after Owner demotes them.');
});

test('owner can promote a member to owner', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $member = attachUserToAccount($account, AccountRole::Member);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $member]), [
            'role' => AccountRole::Owner->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHas('role_changed');

    expect(roleIn($account, $member->id))->toBe(AccountRole::Owner, 'Member pivot role must be updated to Owner after Owner promotes them.');
});

test('sole owner cannot demote themselves to admin', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $owner]), [
            'role' => AccountRole::Admin->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHasErrors('last_owner_blocked');

    expect(roleIn($account, $owner->id))->toBe(AccountRole::Owner, 'Sole Owner pivot role must NOT change — ADR 0002 Owner Protection.');
});

test('sole owner cannot demote themselves to viewer', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $owner]), [
            'role' => AccountRole::Viewer->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHasErrors('last_owner_blocked');

    expect(roleIn($account, $owner->id))->toBe(AccountRole::Owner, 'Sole Owner pivot role must NOT change — ADR 0002 Owner Protection.');
});

test('admin can demote a member to viewer', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
    $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $member]), [
            'role' => AccountRole::Viewer->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHas('role_changed');

    expect(roleIn($account, $member->id))->toBe(AccountRole::Viewer, 'Admin must be able to demote a non-Owner Member to Viewer per the cross-issue contract.');
});

test('admin can demote another admin to member', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $otherAdmin = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
    $account->users()->attach($otherAdmin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $otherAdmin]), [
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect(route('accounts.members.index', $account));
    $response->assertSessionHas('role_changed');

    expect(roleIn($account, $otherAdmin->id))->toBe(AccountRole::Member, 'Admin must be able to demote a peer Admin to Member per the cross-issue contract.');
});

test('admin cannot change an owner role', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $otherOwner = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
    $account->users()->attach($otherOwner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $otherOwner]), [
            'role' => AccountRole::Admin->value,
        ]);

    $response->assertForbidden();

    expect(roleIn($account, $otherOwner->id))->toBe(AccountRole::Owner, 'Owner pivot role must NOT change when Admin attempts the mutation.');
});

test('admin cannot promote a member to owner', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
    $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $member]), [
            'role' => AccountRole::Owner->value,
        ]);

    $response->assertForbidden();

    expect(roleIn($account, $member->id))->toBe(AccountRole::Member, 'Member pivot role must NOT be promoted to Owner by an Admin.');
});

test('viewer cannot change anyone role', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $viewer = attachUserToAccount($account, AccountRole::Viewer);
    $member = attachUserToAccount($account, AccountRole::Member);

    $response = $this
        ->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $member]), [
            'role' => AccountRole::Viewer->value,
        ]);

    $response->assertForbidden();

    expect(roleIn($account, $member->id))->toBe(AccountRole::Member, 'Member pivot role must NOT change when Viewer attempts the mutation.');
});

test('changing role of non member returns 404', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $stranger = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->patch(route('accounts.members.update', [$account, $stranger]), [
            'role' => AccountRole::Viewer->value,
        ]);

    $response->assertNotFound();
});

/**
 * Read the current role of a pivot row, or null if the user is
 * not a member of the account. Bypasses the role cast to keep the
 * test asserting exactly what the database holds.
 */
function roleIn(Account $account, int $userId): ?AccountRole
{
    $raw = $account->users()->whereKey($userId)->first()?->pivot->role;

    return $raw instanceof AccountRole ? $raw : ($raw !== null ? AccountRole::from((string) $raw) : null);
}
