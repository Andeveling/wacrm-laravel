<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApiKeys;
use Tests\TestCase;

/**
 * Per-key throttling for the public API — story 36 (60 req/min per key).
 * Story 37 (per-plan limits) is out of scope until `accounts.plan` exists.
 */
class RateLimitTest extends TestCase
{
    use InteractsWithApiKeys, RefreshDatabase;

    #[Test]
    public function it_allows_up_to_sixty_requests_per_minute_for_a_key(): void
    {
        $apiKey = ApiKey::factory()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        for ($i = 0; $i < 60; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me')->assertOk();
        }
    }

    #[Test]
    public function it_throttles_a_key_independently_of_other_keys(): void
    {
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
    }
}
