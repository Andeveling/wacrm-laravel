<?php

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

it('returns 401 invalid token for a missing key', function () {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token')
        ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token"');
});

it('returns 401 with revoked true for a revoked key', function () {
    $apiKey = ApiKey::factory()->revoked()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token')
        ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token", revoked="true"');
});

it('returns 401 with expired true for an expired key', function () {
    $apiKey = ApiKey::factory()->expired()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    $this->withHeader('Authorization', 'Bearer '.$plaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_token')
        ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token", expired="true"');
});

it('returns 429 with retry after once the rate limit is exceeded', function () {
    $apiKey = ApiKey::factory()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me')->assertOk();
    }

    $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me');

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited')
        ->assertHeader('Retry-After');
});
