<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Campos personalizados del equipo demo. `field_name` se muestra tal cual
 * como etiqueta en la UI, por eso va en español.
 */
class DemoCustomFieldsSeeder extends Seeder
{
    public const SECTOR = 'Sector';

    public const CIUDAD = 'Ciudad';

    public const CUMPLEANOS = 'Cumpleaños';

    public function run(Account $team, User $owner): void
    {
        $definitions = [
            self::SECTOR => 'text',
            self::CIUDAD => 'text',
            self::CUMPLEANOS => 'date',
        ];

        foreach ($definitions as $fieldName => $fieldType) {
            CustomField::firstOrCreate(
                ['account_id' => $team->id, 'field_name' => $fieldName],
                ['user_id' => $owner->id, 'field_type' => $fieldType],
            );
        }
    }
}
