<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiKey;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApiKeys;
use Tests\TestCase;

/**
 * `scope:` middleware enforcement — stories 29-31. No production endpoint
 * declares a scope requirement yet (the v1 catalog is a downstream ticket),
 * so this exercises the middleware against a route registered for the test.
 */
class ScopeEnforcementTest extends TestCase
{
    use InteractsWithApiKeys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([AuthenticateApiKey::class, 'scope:messages:send'])
            ->get('__test/scoped', fn () => response()->json(['data' => 'ok']));
    }

    #[Test]
    public function it_admits_a_key_that_carries_the_required_scope(): void
    {
        $apiKey = ApiKey::factory()->withScopes(ApiScope::MessagesSend->value)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/__test/scoped')
            ->assertOk()
            ->assertJsonPath('data', 'ok');
    }

    #[Test]
    public function it_returns_403_insufficient_scope_when_the_key_lacks_it(): void
    {
        $apiKey = ApiKey::factory()->withScopes(ApiScope::ContactsRead->value)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/__test/scoped')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'insufficient_scope')
            ->assertHeader('WWW-Authenticate', 'Bearer error="insufficient_scope", scope="messages:send"');
    }

    #[Test]
    public function it_returns_403_when_the_key_carries_no_scopes_at_all(): void
    {
        $apiKey = ApiKey::factory()->create(['scopes' => []]);
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/__test/scoped')
            ->assertStatus(403);
    }
}
