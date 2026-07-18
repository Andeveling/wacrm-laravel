<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApiKeys;
use Tests\TestCase;

/**
 * RFC 6750 error format for the `api_key` guard — stories 32-35.
 */
class ApiErrorFormatTest extends TestCase
{
    use InteractsWithApiKeys, RefreshDatabase;

    #[Test]
    public function it_returns_401_invalid_token_for_a_missing_key(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token')
            ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token"');
    }

    #[Test]
    public function it_returns_401_with_revoked_true_for_a_revoked_key(): void
    {
        $apiKey = ApiKey::factory()->revoked()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token')
            ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token", revoked="true"');
    }

    #[Test]
    public function it_returns_401_with_expired_true_for_an_expired_key(): void
    {
        $apiKey = ApiKey::factory()->expired()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token')
            ->assertHeader('WWW-Authenticate', 'Bearer error="invalid_token", expired="true"');
    }

    #[Test]
    public function it_returns_429_with_retry_after_once_the_rate_limit_is_exceeded(): void
    {
        $apiKey = ApiKey::factory()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        for ($i = 0; $i < 60; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me')->assertOk();
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)->getJson('/api/v1/me');

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');
    }
}
