<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $personal = Account::createPersonal();
        $personal->users()->attach($user->id, [
            'role' => AccountRole::Owner->value,
            'joined_at' => now(),
        ]);
    }
}
