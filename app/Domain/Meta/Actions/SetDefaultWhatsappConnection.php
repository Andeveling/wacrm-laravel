<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Designate at most one Active connection as the Account default.
 * The id arrives as a plain string — implicit binding would resolve
 * before EnsureCurrentAccount binds the tenant.
 */
final class SetDefaultWhatsappConnection
{
    public function __invoke(
        CurrentAccount $account,
        string $connection,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        return DB::transaction(function () use ($connection, $responder): RedirectResponse {
            $connections = WhatsappPhoneNumberConnection::query()
                ->lockForUpdate()
                ->get();

            $phoneConnection = $connections->firstWhere('id', $connection);

            if (! $phoneConnection instanceof WhatsappPhoneNumberConnection) {
                abort(404);
            }

            if ($phoneConnection->readiness !== WhatsappConnectionReadiness::Active) {
                return $responder->respond(WhatsappConnectionResult::failure(
                    'Solo una conexión activa puede ser el remitente predeterminado.',
                ));
            }

            foreach ($connections as $item) {
                $shouldBeDefault = $item->id === $phoneConnection->id;

                if ($item->is_default === $shouldBeDefault) {
                    continue;
                }

                $item->is_default = $shouldBeDefault;
                $item->save();
            }

            return $responder->respond(WhatsappConnectionResult::success(
                'Remitente predeterminado actualizado.',
            ));
        });
    }
}
