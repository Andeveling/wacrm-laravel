<?php

use App\Models\Account;
use App\Models\ApiKey;
use App\Support\ApiKeyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

it('resolves the authenticated api key from a valid bearer', function () {
    $apiKey = ApiKey::factory()->create();

    $plaintext = $this->reissuePlaintext($apiKey);
    $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.key.id', $apiKey->id);
});

it('returns null when the authorization header is missing', function () {
    ApiKey::factory()->create();

    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token');
});

it('returns null for an unknown key hash', function () {
    $plaintext = ApiKeyToken::issue('live')['plaintext'];

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token');
});

it('returns null for a revoked key', function () {
    $apiKey = ApiKey::factory()->revoked()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

it('returns null for an expired key', function () {
    $apiKey = ApiKey::factory()->expired()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

it('caches the resolved user within a single request', function () {
    $apiKey = ApiKey::factory()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    // One DB hit during auth, then the response is built without another lookup.
    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me');

    $response->assertOk();
    $queries = DB::getQueryLog();

    $keyHashLookups = array_filter(
        $queries,
        fn (array $q): bool => str_contains($q['query'], 'api_keys')
            && str_contains($q['query'], 'key_hash'),
    );

    expect($keyHashLookups)->toHaveCount(1, 'Guard should cache the lookup so /api/v1/me does not hit api_keys twice.');
});

it('binds the key account to the tenant scope container', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $apiKey = ApiKey::factory()->for($account)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.account.id', $account->id)
        ->assertJsonPath('data.account.name', 'Acme Co');
});

test('creator user can be null when the minter was deleted', function () {
    $account = Account::factory()->create();
    $apiKey = ApiKey::factory()->for($account)->create(['created_by' => null]);
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertOk();
});
