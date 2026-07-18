<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_active_when_neither_revoked_nor_expired(): void
    {
        $apiKey = ApiKey::factory()->create();

        $this->assertTrue($apiKey->isActive());
    }

    #[Test]
    public function it_is_inactive_when_revoked(): void
    {
        $apiKey = ApiKey::factory()->revoked()->create();

        $this->assertFalse($apiKey->isActive());
    }

    #[Test]
    public function it_is_inactive_when_expired(): void
    {
        $apiKey = ApiKey::factory()->expired()->create();

        $this->assertFalse($apiKey->isActive());
    }

    #[Test]
    public function it_grants_a_null_scope_to_any_active_key(): void
    {
        $apiKey = ApiKey::factory()->create(['scopes' => []]);

        $this->assertTrue($apiKey->hasScope(null));
    }

    #[Test]
    public function it_grants_a_scope_when_listed_in_the_scopes_array(): void
    {
        $apiKey = ApiKey::factory()
            ->withScopes(ApiScope::MessagesSend->value, ApiScope::BroadcastsSend->value)
            ->create();

        $this->assertTrue($apiKey->hasScope(ApiScope::MessagesSend));
        $this->assertTrue($apiKey->hasScope(ApiScope::BroadcastsSend));
        $this->assertFalse($apiKey->hasScope(ApiScope::ContactsWrite));
    }

    #[Test]
    public function it_belongs_to_an_account_and_a_creator(): void
    {
        $account = Account::factory()->create(['name' => 'Acme Co']);
        $creator = User::factory()->create();
        $apiKey = ApiKey::factory()
            ->for($account)
            ->for($creator, 'creator')
            ->create();

        $this->assertSame('Acme Co', $apiKey->account->name);
        $this->assertSame($creator->id, $apiKey->creator->id);
    }
}
