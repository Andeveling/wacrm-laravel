<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Http\Requests\Automations\StoreAutomationRequest;
use App\Models\Automation;
use App\Models\Enums\AutomationConnectionMode;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StoreAutomation
{
    /**
     * @var list<string>
     */
    private const INBOUND_TRIGGERS = [
        'new_message_received',
        'first_inbound_message',
        'keyword_match',
        'interactive_reply',
    ];

    public function __invoke(
        StoreAutomationRequest $request,
        CurrentAccount $account,
    ): RedirectResponse {
        abort_unless($account->isMember(), 403);

        $data = $request->validated();

        $automation = DB::transaction(function () use ($data, $request): Automation {
            $mode = AutomationConnectionMode::from($data['connection_mode']);
            $isInbound = in_array($data['trigger_type'], self::INBOUND_TRIGGERS, true);

            if ($mode === AutomationConnectionMode::Trigger && ! $isInbound) {
                throw ValidationException::withMessages([
                    'connection_mode' => 'Las automatizaciones salientes deben fijar una conexión activa.',
                ]);
            }

            $connectionId = null;

            if ($mode === AutomationConnectionMode::Pinned) {
                $connection = WhatsappPhoneNumberConnection::query()
                    ->whereKey($data['connection_id'] ?? null)
                    ->where('readiness', WhatsappConnectionReadiness::Active)
                    ->first();

                if (! $connection instanceof WhatsappPhoneNumberConnection) {
                    throw ValidationException::withMessages([
                        'connection_id' => 'Selecciona una conexión WhatsApp activa.',
                    ]);
                }

                $connectionId = $connection->id;
            }

            return Automation::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'trigger_type' => $data['trigger_type'],
                'connection_mode' => $mode,
                'connection_id' => $connectionId,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);
        });

        return to_route('automations.edit', $automation);
    }
}
