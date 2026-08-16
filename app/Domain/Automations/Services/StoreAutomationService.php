<?php

declare(strict_types=1);

namespace App\Domain\Automations\Services;

use App\Domain\Meta\Services\ActiveWhatsappConnectionResolver;
use App\Models\Automation;
use App\Models\Enums\AutomationConnectionMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StoreAutomationService
{
    /** @var list<string> */
    private const INBOUND_TRIGGERS = ['new_message_received', 'first_inbound_message', 'keyword_match', 'interactive_reply'];

    public function __construct(private ActiveWhatsappConnectionResolver $connections) {}

    /**
     * @param  array{name: string, trigger_type: string, connection_mode: string, connection_id?: string|null, is_active?: bool}  $data
     */
    public function store(array $data, int $userId): Automation
    {
        $automation = DB::transaction(function () use ($data, $userId): Automation {
            $mode = AutomationConnectionMode::from($data['connection_mode']);
            $isInbound = in_array($data['trigger_type'], self::INBOUND_TRIGGERS, true);

            if ($mode === AutomationConnectionMode::Trigger && ! $isInbound) {
                throw ValidationException::withMessages([
                    'connection_mode' => 'Las automatizaciones salientes deben fijar una conexión activa.',
                ]);
            }

            $connectionId = null;

            if ($mode === AutomationConnectionMode::Pinned) {
                $connection = $this->connections->find($data['connection_id'] ?? null);

                if ($connection === null) {
                    throw ValidationException::withMessages([
                        'connection_id' => 'Selecciona una conexión WhatsApp activa.',
                    ]);
                }

                $connectionId = $connection->id;
            }

            return Automation::query()->create([
                'user_id' => $userId,
                'name' => $data['name'],
                'trigger_type' => $data['trigger_type'],
                'connection_mode' => $mode,
                'connection_id' => $connectionId,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
        });

        return $automation;
    }
}
