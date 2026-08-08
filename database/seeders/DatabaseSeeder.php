<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Escenario demo (issue #41). Cada seeder es idempotente por entidad:
 * `firstOrCreate` sobre la restricción única natural de su tabla, así que
 * re-ejecutar `db:seed` solo recrea lo que se borró.
 *
 * Produce:
 *  - test@example.com con su cuenta Personal (invariante de producción).
 *  - Un equipo "WACRM Demo" con datos coherentes de CRM WhatsApp para que
 *    la app local se vea poblada.
 *
 * El contenido visible en la UI está en español.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seeders del equipo demo, en orden de dependencia. Todos reciben la
     * cuenta y su Owner; lo demás lo consultan de la base.
     *
     * @var list<class-string<Seeder>>
     */
    private const DEMO_SEEDERS = [
        DemoCustomFieldsSeeder::class,
        DemoTagsSeeder::class,
        DemoContactsSeeder::class,
        DemoConversationsSeeder::class,
        DemoPipelineSeeder::class,
        DemoBroadcastsSeeder::class,
        DemoAutomationsSeeder::class,
        DemoFlowsSeeder::class,
    ];

    public function run(): void
    {
        config(['app.faker_locale' => 'es_ES']);

        DB::transaction(function (): void {
            $this->call(TestUserSeeder::class);
            $owner = User::where('email', DemoCredentials::TEST_USER_EMAIL)->firstOrFail();

            $this->callWith(DemoTeamSeeder::class, ['owner' => $owner]);
            $team = Account::where('name', DemoTeamSeeder::ACCOUNT_NAME)->firstOrFail();

            // AccountScope es fail-closed (WHERE 1=0) sin binding de tenant.
            // Enlazar el equipo hace que las consultas scoped funcionen.
            app()->instance(AccountScope::CONTAINER_KEY, $team->id);

            try {
                foreach (self::DEMO_SEEDERS as $seeder) {
                    $this->callWith($seeder, ['team' => $team, 'owner' => $owner]);
                }
            } finally {
                app()->forgetInstance(AccountScope::CONTAINER_KEY);
            }
        });

        $this->printDemoCredentials();
    }

    /**
     * Cuando el seeder corre sin un Command explícito (tests, runtime),
     * `$this->command` es null y no se imprime nada.
     */
    private function printDemoCredentials(): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        $command->info('');
        $command->info('Accesos demo (contraseña: '.DemoCredentials::PASSWORD.'):');
        $command->info('  '.DemoCredentials::TEST_USER_EMAIL);

        foreach (DemoCredentials::TEAM_MEMBERS as $email => $member) {
            $command->info("  {$email}  (rol: {$member['role']->value})");
        }
    }
}
