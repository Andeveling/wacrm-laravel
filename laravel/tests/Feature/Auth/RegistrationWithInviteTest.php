<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationWithInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_register_page_preserves_the_invite_token(): void
    {
        $token = Str::random(48);

        $this->get(route('register', ['invite' => $token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/register')
                ->where('invite', $token)
            );
    }

    public function test_registration_with_invite_keeps_personal_account_and_joins_team(): void
    {
        $inviter = User::factory()->create();
        $team = Account::factory()->create(['name' => 'Acme Team']);
        $team->users()->attach($inviter->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $token = Str::random(48);

        $invitation = Invitation::factory()
            ->for($team)
            ->for($inviter, 'inviter')
            ->create([
                'role' => AccountRole::Member->value,
                'token_hash' => hash('sha256', $token),
            ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $token,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertDatabaseCount('accounts', 2);
        $this->assertDatabaseHas('accounts', ['type' => AccountType::Personal->value]);
        dump($user->accounts()->get()->toArray());
        $personal = $user->accounts()->where('type', AccountType::Personal->value)->firstOrFail();
        $teamMembership = $user->accounts()->whereKey($team->id)->firstOrFail();

        $this->assertSame(AccountRole::Owner, $personal->pivot->role);
        $this->assertSame(AccountRole::Member, $teamMembership->pivot->role);
        $this->assertSame($personal->id, session('current_account_id'));
        $this->assertTrue($user->accounts()->count() === 2);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($user->id, $invitation->fresh()->accepted_by);
        $response->assertSessionHas('success', 'Te uniste a Acme Team — Ir');
    }

    public function test_registration_with_invalid_invite_rolls_back_user_and_personal_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => 'invalid-token',
        ]);

        $response->assertSessionHasErrors('invite');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertNull(session('current_account_id'));
    }

    public function test_registration_with_expired_invite_rolls_back_user_and_personal_account(): void
    {
        $inviter = User::factory()->create();
        $team = Account::factory()->create();
        $team->users()->attach($inviter->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $token = Str::random(48);

        Invitation::factory()
            ->for($team)
            ->for($inviter, 'inviter')
            ->expired()
            ->create(['token_hash' => hash('sha256', $token)]);

        $response = $this->post(route('register.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $token,
        ]);

        $response->assertSessionHasErrors('invite');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertNull(session('current_account_id'));
    }
}
