<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('inbox stays on its own page when the account has no whatsapp connection', function () {
    [$user, $account] = memberWithRole('admin');

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('inbox'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inbox'));
});
