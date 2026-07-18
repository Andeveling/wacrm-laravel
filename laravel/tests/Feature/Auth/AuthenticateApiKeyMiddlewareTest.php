<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use App\Models\User;
use App\Support\ApiKeyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticateApiKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_requests_without_an_authorization_header(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson([
                'error' => [
                    'code' => 'invalid_token',
                    'message' => 'Missing, malformed, or unknown API key.',
                ],
            ]);
    }

    #[Test]
    public function it_rejects_requests_with_a_malformed_bearer(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-key')
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    #[Test]
    public function it_admits_requests_with_a_valid_key_and_routes_through(): void
    {
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
    }

    #[Test]
    public function it_binds_the_key_account_to_the_tenant_scope_for_downstream_models(): void
    {
        $account = Account::factory()->create();
        $apiKey = ApiKey::factory()->for($account)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertOk();

        // The middleware writes the account_id into the container before the
        // route closure runs, so any tenant-scoped model queries in this
        // request see only this account's rows.
        $this->assertTrue(App::bound(\App\Models\Scopes\AccountScope::CONTAINER_KEY));
        $this->assertSame($account->id, App::make(\App\Models\Scopes\AccountScope::CONTAINER_KEY));
    }

    private function reissuePlaintext(ApiKey $apiKey): string
    {
        $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

        $apiKey->forceFill(['key_hash' => ApiKeyToken::hash($plaintext)])->save();

        return $plaintext;
    }
}
