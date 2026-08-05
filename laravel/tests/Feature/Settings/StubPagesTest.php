<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('overview page renders with ten panels', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/overview')
            ->has('panels', 10),
        );
});

test('overview page marks baseline panels as disponible', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.overview'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('panels.0.slug', 'profile')
            ->where('panels.0.status', 'Disponible')
            ->where('panels.1.slug', 'security')
            ->where('panels.1.status', 'Disponible')
            ->where('panels.2.slug', 'appearance')
            ->where('panels.2.status', 'Disponible')
            ->where('panels.3.slug', 'members')
            ->where('panels.3.status', 'Disponible'),
        );
});

test('overview page marks api keys as disponible', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.overview'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('panels.4.slug', 'api-keys')
            ->where('panels.4.status', 'Disponible'),
        );
});

test('whatsapp stub page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/whatsapp'),
        );
});

test('templates stub page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.templates'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/templates'),
        );
});

test('quick replies stub page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.quick-replies'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/quick-replies'),
        );
});

test('fields stub page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.fields'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/fields'),
        );
});

test('deals stub page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.deals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/deals'),
        );
});

test('profile still renders after overview takeover', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile'),
        );
});

test('overview redirects guests to login', function () {
    $this->get(route('settings.overview'))
        ->assertRedirect(route('login'));
});
