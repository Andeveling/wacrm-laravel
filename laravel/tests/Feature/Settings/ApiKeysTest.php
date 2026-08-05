<?php

use App\Models\Account;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $response = $this->get(route('settings.api-keys'));

    $response->assertRedirect(route('login'));
});

test('member sees the roster but cannot manage', function () {
    [$member, $account] = memberWithRole('member');
    ApiKey::factory()->for($account)->create();

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.api-keys'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/api-keys')
        ->where('canManage', false)
        ->has('keys', 1));
});

test('member cannot create a key', function () {
    [$member, $account] = memberWithRole('member');

    $response = $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.api-keys.store'), ['name' => 'Should fail']);

    $response->assertForbidden();
    expect(ApiKey::count())->toBe(0);
});

test('admin can create a key and sees the plaintext once', function () {
    [$admin, $account] = memberWithRole('admin');

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('settings.api-keys.store'), [
            'name' => 'Integración de facturación',
            'scopes' => ['contacts:read'],
        ]);

    $response->assertRedirect(route('settings.api-keys'));

    $key = ApiKey::sole();
    expect($key->account_id)->toBe($account->id);
    expect($key->name)->toBe('Integración de facturación');
    expect($key->scopes)->toBe(['contacts:read']);

    $follow = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('settings.api-keys'));

    $follow->assertInertia(fn ($page) => $page
        ->component('settings/api-keys')
        ->where('newKeyPlaintext', fn ($plaintext) => str_starts_with($plaintext, 'wacrm_live_')));
});

test('admin can revoke a key', function () {
    [$admin, $account] = memberWithRole('admin');
    $key = ApiKey::factory()->for($account)->create();

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.api-keys.destroy', $key));

    $response->assertRedirect();
    expect($key->fresh()->revoked_at)->not->toBeNull();
});

test('a key from another account cannot be revoked', function () {
    [$admin, $account] = memberWithRole('admin');
    $otherAccount = Account::factory()->create();
    $foreignKey = ApiKey::factory()->for($otherAccount)->create();

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('settings.api-keys.destroy', $foreignKey));

    $response->assertNotFound();
    expect($foreignKey->fresh()->revoked_at)->toBeNull();
});
