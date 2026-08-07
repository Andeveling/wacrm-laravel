<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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

    // The pivot table has no timestamps columns at all — asserting their
    // absence from the schema, rather than querying a column that does
    // not exist, is what "round trips without timestamps columns" means.
    expect(Schema::hasColumn('account_user', 'created_at'))->toBeFalse();
    expect(Schema::hasColumn('account_user', 'updated_at'))->toBeFalse();
});
