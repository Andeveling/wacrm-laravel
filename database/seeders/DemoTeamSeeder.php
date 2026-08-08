<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuenta de equipo demo con un miembro por rol, además del Owner.
 */
class DemoTeamSeeder extends Seeder
{
    public const ACCOUNT_NAME = 'WACRM Demo';

    public function run(User $owner): void
    {
        $team = Account::firstOrCreate(
            ['name' => self::ACCOUNT_NAME, 'type' => AccountType::Team->value],
        );

        AccountUser::firstOrCreate(
            ['account_id' => $team->id, 'user_id' => $owner->id],
            ['role' => AccountRole::Owner->value, 'joined_at' => now()->subMonths(6)],
        );

        foreach (DemoCredentials::TEAM_MEMBERS as $email => $member) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $member['name'],
                    'password' => Hash::make(DemoCredentials::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            AccountUser::firstOrCreate(
                ['account_id' => $team->id, 'user_id' => $user->id],
                ['role' => $member['role']->value, 'joined_at' => now()->subMonths(3)],
            );
        }
    }
}
