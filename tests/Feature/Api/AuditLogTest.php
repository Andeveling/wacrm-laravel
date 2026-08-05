<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Models\Account;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\Enums\ApiScope;
use App\Models\Scopes\AccountScope;
use App\Support\ApiKeyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware([AuthenticateApiKey::class, 'scope:messages:send'])
        ->get('__test/scoped', fn () => response()->json(['data' => 'ok']));
});

test('revoking a key deactivates it immediately', function () {
    $apiKey = ApiKey::factory()->create();

    $apiKey->revoke();

    expect($apiKey->fresh()->isRevoked())->toBeTrue();
    expect($apiKey->fresh()->isActive())->toBeFalse();
});

test('rotating a key mints a replacement and gives the old one a 24h grace period', function () {
    $apiKey = ApiKey::factory()
        ->withScopes(ApiScope::MessagesSend->value, ApiScope::ContactsRead->value)
        ->create();

    $newKey = $apiKey->rotate();

    expect($newKey->id)->not->toBe($apiKey->id);
    expect($newKey->account_id)->toBe($apiKey->account_id);
    expect($newKey->scopes)->toBe($apiKey->scopes);
    expect($newKey->plaintextToken)->not->toBeNull();
    expect(ApiKeyToken::hash($newKey->plaintextToken))->toBe($newKey->key_hash);

    $apiKey->refresh();
    expect($apiKey->isRevoked())->toBeFalse();
    expect($apiKey->isActive())->toBeTrue();
    expect($apiKey->expires_at)->not->toBeNull();
    expect($apiKey->expires_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();
});

test('a successful request is logged with method path status and duration', function () {
    $account = Account::factory()->create();
    $apiKey = ApiKey::factory()->for($account)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertOk();

    $row = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->sole();

    expect($row->api_key_id)->toBe($apiKey->id);
    expect($row->account_id)->toBe($account->id);
    expect($row->method)->toBe('GET');
    expect($row->path)->toBe('/api/v1/me');
    expect($row->status)->toBe(200);
    expect($row->duration_ms)->toBeGreaterThanOrEqual(0);

    expect($apiKey->fresh()->last_used_at)->not->toBeNull();
});

test('a failed request is logged too with the scope it needed', function () {
    $apiKey = ApiKey::factory()->withScopes(ApiScope::ContactsRead->value)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/__test/scoped')
        ->assertStatus(403);

    $row = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->sole();

    expect($row->status)->toBe(403);
    expect($row->scope_used)->toBe('messages:send');
});

test('an unauthenticated request is not logged', function () {
    $this->getJson('/api/v1/me')->assertStatus(401);

    expect(ApiKeyRequest::withoutGlobalScope(AccountScope::class)->count())->toBe(0);
});

test('audit rows can be filtered by account and date range', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'created_at' => now()->subDays(1)]);
    ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'created_at' => now()->subDays(10)]);
    ApiKeyRequest::factory()->create(['account_id' => $accountB->id, 'created_at' => now()->subDays(1)]);

    $rows = ApiKeyRequest::withoutGlobalScope(AccountScope::class)
        ->forAccountBetween($accountA->id, now()->subDays(2), now())
        ->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->account_id)->toBe($accountA->id);
});

test('the audit command lists rows for an account in a date range', function () {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'path' => 'api/v1/inside', 'created_at' => now()->subDays(1)]);
    ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'path' => 'api/v1/outside', 'created_at' => now()->subDays(10)]);
    ApiKeyRequest::factory()->create(['account_id' => $accountB->id, 'path' => 'api/v1/other', 'created_at' => now()->subDays(1)]);

    $this->artisan('api-keys:audit', [
        '--account' => $accountA->id,
        '--from' => now()->subDays(2)->toDateTimeString(),
        '--to' => now()->toDateTimeString(),
    ])
        ->expectsOutputToContain('api/v1/inside')
        ->doesntExpectOutputToContain('api/v1/outside')
        ->doesntExpectOutputToContain('api/v1/other')
        ->assertSuccessful();
});

test('the audit command fails without an account', function () {
    $this->artisan('api-keys:audit')->assertFailed();
});

test('the prune command deletes rows older than the retention window', function () {
    $stale = ApiKeyRequest::factory()->create(['created_at' => now()->subDays(91)]);
    $fresh = ApiKeyRequest::factory()->create(['created_at' => now()->subDays(1)]);

    Artisan::call('api-keys:prune-audit', ['--older-than' => '90days']);

    $remaining = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->pluck('id');

    expect($remaining->contains($stale->id))->toBeFalse();
    expect($remaining->contains($fresh->id))->toBeTrue();
});

test('the prune command rejects a malformed retention window', function () {
    $this->expectException(InvalidArgumentException::class);

    Artisan::call('api-keys:prune-audit', ['--older-than' => 'ninety']);
});
