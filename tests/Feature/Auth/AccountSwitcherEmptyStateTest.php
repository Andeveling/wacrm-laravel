<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('switcher renders with empty accounts when user belongs to none', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.switch'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/switch')
            ->where('accounts', [])
        );
});
