<?php

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is active when neither revoked nor expired', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->isActive())->toBeTrue();
});

it('is inactive when revoked', function () {
    $apiKey = ApiKey::factory()->revoked()->create();

    expect($apiKey->isActive())->toBeFalse();
});

it('is inactive when expired', function () {
    $apiKey = ApiKey::factory()->expired()->create();

    expect($apiKey->isActive())->toBeFalse();
});

it('grants a null scope to any active key', function () {
    $apiKey = ApiKey::factory()->create(['scopes' => []]);

    expect($apiKey->hasScope(null))->toBeTrue();
});

it('grants a scope when listed in the scopes array', function () {
    $apiKey = ApiKey::factory()
        ->withScopes(ApiScope::MessagesSend->value, ApiScope::BroadcastsSend->value)
        ->create();

    expect($apiKey->hasScope(ApiScope::MessagesSend))->toBeTrue();
    expect($apiKey->hasScope(ApiScope::BroadcastsSend))->toBeTrue();
    expect($apiKey->hasScope(ApiScope::ContactsWrite))->toBeFalse();
});

it('belongs to an account and a creator', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $creator = User::factory()->create();
    $apiKey = ApiKey::factory()
        ->for($account)
        ->for($creator, 'creator')
        ->create();

    expect($apiKey->account->name)->toBe('Acme Co');
    expect($apiKey->creator->id)->toBe($creator->id);
});
