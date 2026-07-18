<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\ApiKeyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_the_authenticated_api_key_from_a_valid_bearer(): void
    {
        $apiKey = ApiKey::factory()->create();

        $plaintext = $this->reissuePlaintext($apiKey);
        $response = $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.key.id', $apiKey->id);
    }

    #[Test]
    public function it_returns_null_when_the_authorization_header_is_missing(): void
    {
        ApiKey::factory()->create();

        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    #[Test]
    public function it_returns_null_for_an_unknown_key_hash(): void
    {
        $plaintext = ApiKeyToken::issue('live')['plaintext'];

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_token');
    }

    #[Test]
    public function it_returns_null_for_a_revoked_key(): void
    {
        $apiKey = ApiKey::factory()->revoked()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_null_for_an_expired_key(): void
    {
        $apiKey = ApiKey::factory()->expired()->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    #[Test]
    public function it_caches_the_resolved_user_within_a_single_request(): void
    {
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

        $this->assertCount(1, $keyHashLookups, 'Guard should cache the lookup so /api/v1/me does not hit api_keys twice.');
    }

    #[Test]
    public function it_binds_the_key_account_to_the_tenant_scope_container(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Co']);
        $apiKey = ApiKey::factory()->for($account)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.account.name', 'Acme Co');
    }

    #[Test]
    public function creator_user_can_be_null_when_the_minter_was_deleted(): void
    {
        $account = Account::factory()->create();
        $apiKey = ApiKey::factory()->for($account)->create(['created_by' => null]);
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    /**
     * Issue a fresh plaintext that hashes to the same digest as the factory row,
     * so tests can drive the guard without persisting the plaintext anywhere.
     */
    private function reissuePlaintext(ApiKey $apiKey): string
    {
        $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

        $apiKey->forceFill(['key_hash' => ApiKeyToken::hash($plaintext)])->save();

        return $plaintext;
    }
}
