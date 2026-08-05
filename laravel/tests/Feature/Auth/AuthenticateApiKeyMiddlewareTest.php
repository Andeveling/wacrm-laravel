<?php

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

it('rejects requests without an authorization header', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson([
            'error' => [
                'code' => 'invalid_token',
                'message' => 'Missing, malformed, or unknown API key.',
            ],
        ]);
});

it('rejects requests with a malformed bearer', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-key')
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

it('admits requests with a valid key and routes through', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $user = User::factory()->create(['name' => 'Minter']);
    $apiKey = ApiKey::factory()
        ->for($account)
        ->for($user, 'creator')
        ->withScopes(ApiScope::MessagesSend->value, ApiScope::ContactsRead->value)
        ->create();

    $plaintext = $this->reissuePlaintext($apiKey);

    $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.account.id', $account->id)
        ->assertJsonPath('data.account.name', 'Acme Co')
        ->assertJsonPath('data.key.id', $apiKey->id)
        ->assertJsonPath('data.key.scopes', [ApiScope::MessagesSend->value, ApiScope::ContactsRead->value]);
});

it('binds the key account to the tenant scope for downstream models', function () {
    $account = Account::factory()->create();
    $apiKey = ApiKey::factory()->for($account)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertOk();

    // The middleware writes the account_id into the container before the
    // route closure runs, so any tenant-scoped model queries in this
    // request see only this account's rows.
    expect(App::bound(AccountScope::CONTAINER_KEY))->toBeTrue();
    expect(App::make(AccountScope::CONTAINER_KEY))->toBe($account->id);
});
