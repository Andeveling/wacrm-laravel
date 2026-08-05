<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware([AuthenticateApiKey::class, 'scope:messages:send'])
        ->get('__test/scoped', fn () => response()->json(['data' => 'ok']));
});

it('admits a key that carries the required scope', function () {
    $apiKey = ApiKey::factory()->withScopes(ApiScope::MessagesSend->value)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/__test/scoped')
        ->assertOk()
        ->assertJsonPath('data', 'ok');
});

it('returns 403 insufficient scope when the key lacks it', function () {
    $apiKey = ApiKey::factory()->withScopes(ApiScope::ContactsRead->value)->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/__test/scoped')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_scope')
        ->assertHeader('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="messages:send"');
});

it('returns 403 when the key carries no scopes at all', function () {
    $apiKey = ApiKey::factory()->create(['scopes' => []]);
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/__test/scoped')
        ->assertStatus(403);
});
