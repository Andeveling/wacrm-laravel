<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Facades\DB;

final class SetDefaultWhatsappConnectionService
{
    public function setDefault(string $connectionId): WhatsappConnectionResult
    {
        return DB::transaction(function () use ($connectionId): WhatsappConnectionResult {
            $connections = WhatsappPhoneNumberConnection::query()
                ->lockForUpdate()
                ->get();

            $phoneConnection = $connections->firstWhere('id', $connectionId);

            if (! $phoneConnection instanceof WhatsappPhoneNumberConnection) {
                abort(404);
            }

            if ($phoneConnection->readiness !== WhatsappConnectionReadiness::Active) {
                return WhatsappConnectionResult::failure(
                    'Solo una conexión activa puede ser el remitente predeterminado.',
                );
            }

            foreach ($connections as $item) {
                $shouldBeDefault = $item->id === $phoneConnection->id;

                if ($item->is_default === $shouldBeDefault && $item->pending_default === false) {
                    continue;
                }

                $item->is_default = $shouldBeDefault;
                $item->pending_default = false;
                $item->save();
            }

            return WhatsappConnectionResult::success('Remitente predeterminado actualizado.');
        });
    }
}
