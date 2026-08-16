<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Services\DisconnectWhatsappConnectionService;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class DisconnectWhatsappConnection
{
    public function __invoke(CurrentAccount $account, string $connection, DisconnectWhatsappConnectionService $service, WhatsappConnectionResponder $responder): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        return $responder->respond($service->disconnect($connection, $account->id()));
    }
}
