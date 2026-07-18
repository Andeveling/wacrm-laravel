<?php

namespace Tests\Feature\Api;

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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithApiKeys;
use Tests\TestCase;

/**
 * Key rotation/revocation + the request audit log — stories 27, 28, 38-40.
 */
class AuditLogTest extends TestCase
{
    use InteractsWithApiKeys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware([AuthenticateApiKey::class, 'scope:messages:send'])
            ->get('__test/scoped', fn () => response()->json(['data' => 'ok']));
    }

    #[Test]
    public function revoking_a_key_deactivates_it_immediately(): void
    {
        $apiKey = ApiKey::factory()->create();

        $apiKey->revoke();

        $this->assertTrue($apiKey->fresh()->isRevoked());
        $this->assertFalse($apiKey->fresh()->isActive());
    }

    #[Test]
    public function rotating_a_key_mints_a_replacement_and_gives_the_old_one_a_24h_grace_period(): void
    {
        $apiKey = ApiKey::factory()
            ->withScopes(ApiScope::MessagesSend->value, ApiScope::ContactsRead->value)
            ->create();

        $newKey = $apiKey->rotate();

        $this->assertNotSame($apiKey->id, $newKey->id);
        $this->assertSame($apiKey->account_id, $newKey->account_id);
        $this->assertSame($apiKey->scopes, $newKey->scopes);
        $this->assertNotNull($newKey->plaintextToken);
        $this->assertSame($newKey->key_hash, ApiKeyToken::hash($newKey->plaintextToken));

        $apiKey->refresh();
        $this->assertFalse($apiKey->isRevoked());
        $this->assertTrue($apiKey->isActive());
        $this->assertNotNull($apiKey->expires_at);
        $this->assertTrue($apiKey->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    #[Test]
    public function a_successful_request_is_logged_with_method_path_status_and_duration(): void
    {
        $account = Account::factory()->create();
        $apiKey = ApiKey::factory()->for($account)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/api/v1/me')
            ->assertOk();

        $row = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->sole();

        $this->assertSame($apiKey->id, $row->api_key_id);
        $this->assertSame($account->id, $row->account_id);
        $this->assertSame('GET', $row->method);
        $this->assertSame('/api/v1/me', $row->path);
        $this->assertSame(200, $row->status);
        $this->assertGreaterThanOrEqual(0, $row->duration_ms);

        $this->assertNotNull($apiKey->fresh()->last_used_at);
    }

    #[Test]
    public function a_failed_request_is_logged_too_with_the_scope_it_needed(): void
    {
        $apiKey = ApiKey::factory()->withScopes(ApiScope::ContactsRead->value)->create();
        $plaintext = $this->reissuePlaintext($apiKey);

        $this->withHeader('Authorization', 'Bearer '.$plaintext)
            ->getJson('/__test/scoped')
            ->assertStatus(403);

        $row = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->sole();

        $this->assertSame(403, $row->status);
        $this->assertSame('messages:send', $row->scope_used);
    }

    #[Test]
    public function an_unauthenticated_request_is_not_logged(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);

        $this->assertSame(0, ApiKeyRequest::withoutGlobalScope(AccountScope::class)->count());
    }

    #[Test]
    public function audit_rows_can_be_filtered_by_account_and_date_range(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'created_at' => now()->subDays(1)]);
        ApiKeyRequest::factory()->create(['account_id' => $accountA->id, 'created_at' => now()->subDays(10)]);
        ApiKeyRequest::factory()->create(['account_id' => $accountB->id, 'created_at' => now()->subDays(1)]);

        $rows = ApiKeyRequest::withoutGlobalScope(AccountScope::class)
            ->forAccountBetween($accountA->id, now()->subDays(2), now())
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame($accountA->id, $rows->first()->account_id);
    }

    #[Test]
    public function the_audit_command_lists_rows_for_an_account_in_a_date_range(): void
    {
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
    }

    #[Test]
    public function the_audit_command_fails_without_an_account(): void
    {
        $this->artisan('api-keys:audit')->assertFailed();
    }

    #[Test]
    public function the_prune_command_deletes_rows_older_than_the_retention_window(): void
    {
        $stale = ApiKeyRequest::factory()->create(['created_at' => now()->subDays(91)]);
        $fresh = ApiKeyRequest::factory()->create(['created_at' => now()->subDays(1)]);

        Artisan::call('api-keys:prune-audit', ['--older-than' => '90days']);

        $remaining = ApiKeyRequest::withoutGlobalScope(AccountScope::class)->pluck('id');

        $this->assertFalse($remaining->contains($stale->id));
        $this->assertTrue($remaining->contains($fresh->id));
    }

    #[Test]
    public function the_prune_command_rejects_a_malformed_retention_window(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Artisan::call('api-keys:prune-audit', ['--older-than' => 'ninety']);
    }
}
