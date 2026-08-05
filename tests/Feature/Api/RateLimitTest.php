<?php

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\InteractsWithApiKeys;

uses(InteractsWithApiKeys::class);
uses(RefreshDatabase::class);

it('allows up to sixty requests per minute for a key', function () {
    $apiKey = ApiKey::factory()->create();
    $plaintext = $this->reissuePlaintext($apiKey);

    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me')->assertOk();
    }
});

it('throttles a key independently of other keys', function () {
    $exhausted = ApiKey::factory()->create();
    $exhaustedPlaintext = $this->reissuePlaintext($exhausted);

    for ($i = 0; $i < 60; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$exhaustedPlaintext)->getJson('/api/v1/me')->assertOk();
    }

    $this->withHeader('Authorization', 'Bearer '.$exhaustedPlaintext)
        ->getJson('/api/v1/me')
        ->assertStatus(429);

    // ApiKeyGuard memoizes the resolved key for the guard instance's
    // lifetime, which — unlike a real request — outlives a single test
    // call. Forget it so the next call re-resolves from its own bearer
    // token, same as a fresh process would.
    Auth::forgetGuards();

    $fresh = ApiKey::factory()->create();
    $freshPlaintext = $this->reissuePlaintext($fresh);

    $this->withHeader('Authorization', 'Bearer '.$freshPlaintext)
        ->getJson('/api/v1/me')
        ->assertOk();
});
