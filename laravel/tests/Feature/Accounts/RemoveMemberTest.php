<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('owner can remove an admin', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $admin = attachUserToAccount($account, AccountRole::Admin);
    $from = membersIndexUrl($account);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->from($from)
        ->delete(route('accounts.members.destroy', [$account, $admin]));

    $response->assertRedirect($from);
    $response->assertSessionHas('member_removed');
    expect($account->users()->whereKey($admin->id)->exists())->toBeFalse('Admin pivot row must be deleted after Owner removes them.');
});

test('owner can remove a member', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $member = attachUserToAccount($account, AccountRole::Member);
    $from = membersIndexUrl($account);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->from($from)
        ->delete(route('accounts.members.destroy', [$account, $member]));

    $response->assertRedirect($from);
    $response->assertSessionHas('member_removed');
    expect($account->users()->whereKey($member->id)->exists())->toBeFalse('Member pivot row must be deleted after Owner removes them.');
});

test('sole owner cannot remove themselves', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('accounts.members.destroy', [$account, $owner]));

    $response->assertForbidden();
    expect($account->users()->whereKey($owner->id)->exists())->toBeTrue('Sole Owner must remain a member of the Account (self-removal blocked).');
});

test('admin cannot remove the sole owner', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $from = membersIndexUrl($account);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from($from)
        ->delete(route('accounts.members.destroy', [$account, $owner]));

    $response->assertRedirect($from);
    $response->assertSessionHasErrors();
    expect($account->users()->whereKey($owner->id)->exists())->toBeTrue('Sole Owner must NOT be removed — ADR 0002 Owner Protection.');
    expect(DB::table('account_user')->where('account_id', $account->id)->count())->toBe(2, 'Both Owner and Admin pivots must remain — LastOwnerBlocked writes nothing.');
});

test('admin can remove an owner when another owner exists', function () {
    $account = Account::factory()->create();
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($ownerA->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($ownerB->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $from = membersIndexUrl($account);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->from($from)
        ->delete(route('accounts.members.destroy', [$account, $ownerB]));

    $response->assertRedirect($from);
    $response->assertSessionHas('member_removed');
    expect($account->users()->whereKey($ownerB->id)->exists())->toBeFalse('Non-sole Owner must be removable when another Owner remains.');
    expect($account->users()->whereKey($ownerA->id)->exists())->toBeTrue();
});

test('viewer cannot remove anyone', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $viewer = attachUserToAccount($account, AccountRole::Viewer);
    $admin = attachUserToAccount($account, AccountRole::Admin);

    $response = $this
        ->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('accounts.members.destroy', [$account, $admin]));

    $response->assertForbidden();
    expect($account->users()->whereKey($admin->id)->exists())->toBeTrue('Viewer must not be able to remove anyone.');
});

test('removing nonexistent member returns 404', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $stranger = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('accounts.members.destroy', [$account, $stranger]));

    $response->assertNotFound();
});

test('personal account of removed user is intact', function () {
    [$account, $owner] = seedAccountWithRole(AccountRole::Owner);
    $admin = attachUserToAccount($account, AccountRole::Admin);

    // Each user auto-owns a Personal account; create one for the admin
    // so we can verify it survives a Team-Account removal.
    $personal = Account::factory()->personal()->create();
    DB::table('account_user')->insert([
        'account_id' => $personal->id,
        'user_id' => $admin->id,
        'role' => AccountRole::Owner->value,
        'joined_at' => now(),
    ]);

    $from = membersIndexUrl($account);

    $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->from($from)
        ->delete(route('accounts.members.destroy', [$account, $admin]))
        ->assertRedirect($from);

    expect($account->users()->whereKey($admin->id)->exists())->toBeFalse('Team-Account membership must be deleted.');
    expect($personal->users()->whereKey($admin->id)->exists())->toBeTrue('Personal Account of the removed user must remain intact.');
    $this->assertDatabaseHas('accounts', ['id' => $personal->id]);
});

/**
 * Build the "back" URL the Responder redirects to. We prefer the
 * `accounts.members.index` named route when present; otherwise fall
 * back to the raw path so tests can run before #43 lands.
 */
function membersIndexUrl(Account $account): string
{
    if (app('router')->getRoutes()->hasNamedRoute('accounts.members.index')) {
        return route('accounts.members.index', $account);
    }

    return "/accounts/{$account->id}/members";
}
