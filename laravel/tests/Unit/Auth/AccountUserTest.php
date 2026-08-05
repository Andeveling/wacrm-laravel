<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('direct create round trips without timestamps columns', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    $pivot = AccountUser::create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'role' => AccountRole::Owner,
        'joined_at' => now(),
    ]);

    expect($pivot)->toBeInstanceOf(AccountUser::class);
    $this->assertDatabaseHas('account_user', [
        'account_id' => $account->id,
        'user_id' => $user->id,
        'role' => AccountRole::Owner->value,
    ]);
    $this->assertDatabaseMissing('account_user', [
        'account_id' => $account->id,
        'user_id' => $user->id,
        'created_at' => now()->toDateTimeString(),
    ]);
});
