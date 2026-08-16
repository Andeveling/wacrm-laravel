<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'password']);
    $this->account = Account::factory()->create();
    $this->account->users()->attach($this->user->id, [
        'role' => AccountRole::Owner->value,
        'joined_at' => now(),
    ]);
});

test('appearance preferences apply, persist, and stay free of javascript errors', function () {
    signInAndSelectAccount($this->user);

    $page = $this->visit('/settings/appearance');
    $page->script("localStorage.clear(); document.cookie = 'appearance=;path=/;max-age=0'");

    $this->visit('/settings/appearance')
        ->assertNoSmoke()
        ->assertAttribute('html', 'data-mode', 'dark')
        ->assertAttribute('html', 'data-theme', 'violet')
        ->click('[data-testid="appearance-mode-light"]')
        ->assertAttribute('html', 'data-mode', 'light')
        ->click('[data-testid="appearance-mode-dark"]')
        ->assertAttribute('html', 'data-mode', 'dark')
        ->click('[data-testid="appearance-mode-system"]')
        ->assertScript("document.documentElement.dataset.mode === (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')")
        ->click('[data-testid="appearance-theme-rose"]')
        ->assertAttribute('html', 'data-theme', 'rose')
        ->assertNoSmoke();

    $this->visit('/settings/appearance')
        ->assertScript("document.documentElement.dataset.mode === (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')")
        ->assertAttribute('html', 'data-theme', 'rose')
        ->assertNoSmoke();
});
