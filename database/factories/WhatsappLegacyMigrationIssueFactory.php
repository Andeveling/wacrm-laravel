<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\WhatsappLegacyMigrationIssueKind;
use App\Models\WhatsappLegacyMigrationIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappLegacyMigrationIssue>
 */
class WhatsappLegacyMigrationIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'kind' => WhatsappLegacyMigrationIssueKind::IncompleteLegacyConfig,
            'details' => ['missing' => ['waba_id']],
            'fingerprint' => fake()->unique()->sha256(),
        ];
    }
}
