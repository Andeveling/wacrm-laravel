<?php

namespace Tests\Feature\Settings;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeysTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Account}
     */
    private function memberWithRole(string $role): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $account->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

        return [$user, $account];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('settings.api-keys'));

        $response->assertRedirect(route('login'));
    }

    public function test_member_sees_the_roster_but_cannot_manage(): void
    {
        [$member, $account] = $this->memberWithRole('member');
        ApiKey::factory()->for($account)->create();

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('settings.api-keys'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/api-keys')
            ->where('canManage', false)
            ->has('keys', 1));
    }

    public function test_member_cannot_create_a_key(): void
    {
        [$member, $account] = $this->memberWithRole('member');

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('settings.api-keys.store'), ['name' => 'Should fail']);

        $response->assertForbidden();
        $this->assertSame(0, ApiKey::count());
    }

    public function test_admin_can_create_a_key_and_sees_the_plaintext_once(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

        $response = $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('settings.api-keys.store'), [
                'name' => 'Integración de facturación',
                'scopes' => ['contacts:read'],
            ]);

        $response->assertRedirect(route('settings.api-keys'));

        $key = ApiKey::sole();
        $this->assertSame($account->id, $key->account_id);
        $this->assertSame('Integración de facturación', $key->name);
        $this->assertSame(['contacts:read'], $key->scopes);

        $follow = $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('settings.api-keys'));

        $follow->assertInertia(fn ($page) => $page
            ->component('settings/api-keys')
            ->where('newKeyPlaintext', fn ($plaintext) => str_starts_with($plaintext, 'wacrm_live_')));
    }

    public function test_admin_can_revoke_a_key(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $key = ApiKey::factory()->for($account)->create();

        $response = $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('settings.api-keys.destroy', $key));

        $response->assertRedirect();
        $this->assertNotNull($key->fresh()->revoked_at);
    }

    public function test_a_key_from_another_account_cannot_be_revoked(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $otherAccount = Account::factory()->create();
        $foreignKey = ApiKey::factory()->for($otherAccount)->create();

        $response = $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('settings.api-keys.destroy', $foreignKey));

        $response->assertNotFound();
        $this->assertNull($foreignKey->fresh()->revoked_at);
    }
}
