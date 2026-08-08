<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Enums\DealStatus;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Embudo de ventas demo con sus etapas y negocios repartidos entre ellas.
 * Los nombres de etapa se muestran tal cual en el tablero. Depende de
 * DemoContactsSeeder.
 */
class DemoPipelineSeeder extends Seeder
{
    private const PIPELINE_NAME = 'Ventas Colombia';

    /**
     * @var list<string>
     */
    private const STAGE_NAMES = ['Nuevo', 'Calificado', 'Propuesta', 'Ganado'];

    public function run(Account $team, User $owner): void
    {
        if (Pipeline::where('account_id', $team->id)->exists()) {
            return;
        }

        $pipeline = Pipeline::create([
            'account_id' => $team->id,
            'user_id' => $owner->id,
            'name' => self::PIPELINE_NAME,
        ]);

        $stages = [];
        foreach (self::STAGE_NAMES as $position => $name) {
            $stages[] = PipelineStage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $name,
                'position' => $position,
            ]);
        }

        $contacts = Contact::where('account_id', $team->id)->get()->all();

        if ($contacts === []) {
            return;
        }

        foreach ($this->dealDefinitions() as $index => $deal) {
            Deal::create([
                'account_id' => $team->id,
                'user_id' => $owner->id,
                'assigned_to' => $owner->id,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stages[min($index, count($stages) - 1)]->id,
                'contact_id' => $contacts[$index % count($contacts)]->id,
                'title' => $deal['title'],
                'value' => fake()->randomFloat(2, 500, 50000),
                'currency' => 'COP',
                'status' => $deal['status'],
            ]);
        }
    }

    /**
     * @return list<array{title: string, status: DealStatus}>
     */
    private function dealDefinitions(): array
    {
        return [
            ['title' => 'Plan Premium - '.fake()->company(), 'status' => DealStatus::Open],
            ['title' => 'Renovación anual '.fake()->company(), 'status' => DealStatus::Open],
            ['title' => 'Migración a plan empresarial', 'status' => DealStatus::Open],
            ['title' => 'Capacitación equipo comercial', 'status' => DealStatus::Open],
            ['title' => 'Integración WhatsApp Business', 'status' => DealStatus::Open],
            ['title' => 'Onboarding '.fake()->company(), 'status' => DealStatus::Won],
            ['title' => 'Soporte prioritario Q'.fake()->numberBetween(1, 4), 'status' => DealStatus::Lost],
            ['title' => 'Licencias adicionales', 'status' => DealStatus::Open],
        ];
    }
}
