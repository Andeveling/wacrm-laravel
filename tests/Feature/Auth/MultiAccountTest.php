<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('registration creates a personal account owned by the user', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $account = $user->accounts()->sole();

    expect($account->name)->toBe('Personal');
    expect($account->type === AccountType::Personal)->toBeTrue();
    expect($account->pivot->role)->toBe(AccountRole::Owner);
    expect(session('current_account_id'))->toBe($account->id);
    expect($user->last_account_id)->toBe($account->id);
});

test('the switch page no longer exists', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Mine']);
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get('/accounts/switch')
        ->assertNotFound();
});

test('settings pages share the current account without the tenant middleware', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Mine']);
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('settings.overview'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentAccount.id', $account->id)
            ->where('currentAccount.name', 'Mine')
            ->where('currentAccount.type', 'team')
            ->where('currentAccount.role', 'owner')
            ->has('accounts', 1)
        );
});

test('authenticated pages share the current account and memberships', function () {
    $user = User::factory()->create();
    $mine = Account::factory()->create(['name' => 'Mine']);
    $mine->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $foreign = Account::factory()->create(['name' => 'Not mine']);
    $foreign->users()->attach(User::factory()->create()->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $mine->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentAccount.id', $mine->id)
            ->where('currentAccount.name', 'Mine')
            ->where('currentAccount.type', 'team')
            ->where('currentAccount.role', 'owner')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Mine')
        );
});

test('shared memberships list teams A to Z and personal last', function () {
    $user = User::factory()->create();
    $personal = Account::factory()->personal()->create();
    $zebra = Account::factory()->create(['name' => 'Zebra']);
    $acme = Account::factory()->create(['name' => 'Acme']);
    $personal->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $zebra->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
    $acme->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $acme->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('accounts', 3)
            ->where('accounts.0.name', 'Acme')
            ->where('accounts.1.name', 'Zebra')
            ->where('accounts.2.name', 'Personal')
            ->where('accounts.2.type', 'personal')
        );
});

test('user can switch to an account they belong to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

    $response->assertRedirect(route('dashboard'));
    expect(session('current_account_id'))->toBe($account->id);
    expect($user->fresh()->last_account_id)->toBe($account->id);
});

test('switching from a section index stays on that section', function (string $from, string $expected) {
    $user = User::factory()->create();
    $fromAccount = Account::factory()->create(['name' => 'Acme']);
    $to = Account::factory()->create(['name' => 'Beta']);
    $fromAccount->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $to->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->from($from)
        ->post(route('accounts.switch.update', $to))
        ->assertRedirect($expected);
})->with([
    'dashboard' => [fn () => route('dashboard'), fn () => route('dashboard')],
    'inbox' => [fn () => route('inbox'), fn () => route('inbox')],
    'contacts' => [fn () => route('contacts'), fn () => route('contacts')],
    'pipelines' => [fn () => route('pipelines'), fn () => route('pipelines')],
    'broadcasts' => [fn () => route('broadcasts'), fn () => route('broadcasts')],
    'automations' => [fn () => route('automations'), fn () => route('automations')],
    'flows' => [fn () => route('flows'), fn () => route('flows')],
    'agents' => [fn () => route('agents'), fn () => route('agents')],
    'notifications' => [fn () => route('notifications'), fn () => route('notifications')],
    'settings' => [fn () => route('settings.overview'), fn () => route('settings.overview')],
]);

test('switching from an unclassifiable url goes to the dashboard', function () {
    $user = User::factory()->create();
    $to = Account::factory()->create(['name' => 'Beta']);
    $to->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->from('/accounts/'.$to->id.'/members')
        ->post(route('accounts.switch.update', $to))
        ->assertRedirect(route('dashboard'));
});

test('switching from a settings subpage goes to the settings index', function () {
    $user = User::factory()->create();
    $to = Account::factory()->create(['name' => 'Beta']);
    $to->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->from(route('settings.whatsapp'))
        ->post(route('accounts.switch.update', $to))
        ->assertRedirect(route('settings.overview'));
});

test('switching from a contact detail goes to the contacts index', function () {
    $user = User::factory()->create();
    $from = Account::factory()->create(['name' => 'Acme']);
    $to = Account::factory()->create(['name' => 'Beta']);
    $from->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $to->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $response = $this->actingAs($user)
        ->from('/contacts/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/notes')
        ->post(route('accounts.switch.update', $to));

    $response->assertRedirect(route('contacts'));
});

test('user cannot switch to an account they do not belong to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

    $response->assertForbidden();
    expect(session('current_account_id'))->toBeNull();
});
