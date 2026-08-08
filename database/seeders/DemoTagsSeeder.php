<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Etiquetas del equipo demo. El nombre se muestra tal cual en la UI.
 */
class DemoTagsSeeder extends Seeder
{
    public const VIP = 'VIP';

    /**
     * @var array<string, string>
     */
    private const TAG_COLORS = [
        'Prospecto' => '#3b82f6',
        'Cliente' => '#10b981',
        self::VIP => '#f59e0b',
    ];

    public function run(Account $team, User $owner): void
    {
        foreach (self::TAG_COLORS as $name => $color) {
            Tag::firstOrCreate(
                ['account_id' => $team->id, 'name' => $name],
                ['user_id' => $owner->id, 'color' => $color],
            );
        }
    }
}
