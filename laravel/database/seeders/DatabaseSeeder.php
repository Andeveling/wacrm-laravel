<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Idempotent and transactional: re-running `db:seed` must never crash on
     * the test user already existing, and a user must never be committed
     * without their Personal account attached (that combination is what
     * previously stranded test@example.com in the account switcher with no
     * account to select).
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::where('email', 'test@example.com')->first()
                ?? User::factory()->create([
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);

            if ($user->accounts()->exists()) {
                return;
            }

            $personal = Account::createPersonal();
            $personal->users()->attach($user->id, [
                'role' => AccountRole::Owner->value,
                'joined_at' => now(),
            ]);
        });
    }
}
