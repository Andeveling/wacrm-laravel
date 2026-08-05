<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('owner has full control including billing and deletion', function () {
    [$owner, $account] = memberWithRole('owner');
    $gate = Gate::forUser($owner);

    expect($gate->allows('manageMembers', $account))->toBeTrue();
    expect($gate->allows('editSettings', $account))->toBeTrue();
    expect($gate->allows('manageBilling', $account))->toBeTrue();
    expect($gate->allows('delete', $account))->toBeTrue();
    expect($gate->allows('writeOperationalData', $account))->toBeTrue();
    expect($gate->allows('viewOperationalData', $account))->toBeTrue();
});

test('admin manages members and settings but not billing or deletion', function () {
    [$admin, $account] = memberWithRole('admin');
    $gate = Gate::forUser($admin);

    expect($gate->allows('manageMembers', $account))->toBeTrue();
    expect($gate->allows('editSettings', $account))->toBeTrue();
    expect($gate->allows('writeOperationalData', $account))->toBeTrue();
    expect($gate->allows('manageBilling', $account))->toBeFalse();
    expect($gate->allows('delete', $account))->toBeFalse();
});

test('member writes operational data but cannot manage the account', function () {
    [$member, $account] = memberWithRole('member');
    $gate = Gate::forUser($member);

    expect($gate->allows('writeOperationalData', $account))->toBeTrue();
    expect($gate->allows('viewOperationalData', $account))->toBeTrue();
    expect($gate->allows('manageMembers', $account))->toBeFalse();
    expect($gate->allows('editSettings', $account))->toBeFalse();
    expect($gate->allows('manageBilling', $account))->toBeFalse();
    expect($gate->allows('delete', $account))->toBeFalse();
});

test('viewer has read only access', function () {
    [$viewer, $account] = memberWithRole('viewer');
    $gate = Gate::forUser($viewer);

    expect($gate->allows('viewOperationalData', $account))->toBeTrue();
    expect($gate->allows('writeOperationalData', $account))->toBeFalse();
    expect($gate->allows('manageMembers', $account))->toBeFalse();
    expect($gate->allows('editSettings', $account))->toBeFalse();
    expect($gate->allows('manageBilling', $account))->toBeFalse();
    expect($gate->allows('delete', $account))->toBeFalse();
});

test('non member has no access at all', function () {
    $outsider = User::factory()->create();
    $account = Account::factory()->create();
    $gate = Gate::forUser($outsider);

    expect($gate->allows('viewOperationalData', $account))->toBeFalse();
    expect($gate->allows('writeOperationalData', $account))->toBeFalse();
    expect($gate->allows('manageMembers', $account))->toBeFalse();
    expect($gate->allows('editSettings', $account))->toBeFalse();
    expect($gate->allows('manageBilling', $account))->toBeFalse();
    expect($gate->allows('delete', $account))->toBeFalse();
});
