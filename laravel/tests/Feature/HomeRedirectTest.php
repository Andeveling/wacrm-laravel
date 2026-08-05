<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('guest visiting root is redirected to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login', absolute: false));
});

test('authenticated user visiting root is redirected to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});
