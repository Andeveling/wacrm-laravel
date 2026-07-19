<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountSwitcherEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_switcher_renders_with_empty_accounts_when_user_belongs_to_none(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('accounts.switch'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('accounts/switch')
                ->where('accounts', [])
            );
    }
}
