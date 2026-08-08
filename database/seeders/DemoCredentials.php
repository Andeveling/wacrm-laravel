<?php

namespace Database\Seeders;

use App\Models\Enums\AccountRole;

/**
 * Cuentas de acceso del escenario demo. Viven aquí porque las comparten
 * el seeder que las crea y el que las imprime al final de `db:seed`.
 */
final class DemoCredentials
{
    public const TEST_USER_EMAIL = 'test@example.com';

    public const PASSWORD = 'password';

    /**
     * Miembros sintéticos del equipo demo, uno por rol.
     *
     * @var array<string, array{name: string, role: AccountRole}>
     */
    public const TEAM_MEMBERS = [
        'admin@demo.test' => ['name' => 'Administrador Demo', 'role' => AccountRole::Admin],
        'agent@demo.test' => ['name' => 'Agente Demo', 'role' => AccountRole::Member],
        'viewer@demo.test' => ['name' => 'Observador Demo', 'role' => AccountRole::Viewer],
    ];
}
