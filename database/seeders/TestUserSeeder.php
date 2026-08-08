<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuario de pruebas con su cuenta Personal, invariante de producción:
 * todo usuario pertenece a una cuenta Personal como Owner.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => DemoCredentials::TEST_USER_EMAIL],
            [
                'name' => 'Usuario de Prueba',
                'password' => Hash::make(DemoCredentials::PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $personal = Account::firstOrCreate(
            ['name' => Account::PERSONAL_NAME, 'type' => AccountType::Personal->value],
        );

        if (! $personal->users()->whereKey($user->id)->exists()) {
            $personal->users()->attach($user->id, [
                'role' => AccountRole::Owner->value,
                'joined_at' => now(),
            ]);
        }
    }
}
