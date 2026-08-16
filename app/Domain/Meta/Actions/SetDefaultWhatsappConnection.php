<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Services\SetDefaultWhatsappConnectionService;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

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
        SetDefaultWhatsappConnectionService $service,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        return $responder->respond($service->setDefault($connection));
    }
}
