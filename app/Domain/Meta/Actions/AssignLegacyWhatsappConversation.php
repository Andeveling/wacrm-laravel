<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Services\AssignLegacyWhatsappConversationService;
use App\Http\Requests\Meta\AssignLegacyWhatsappConversationRequest;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

/**
 * Assign an explicit connection to a conversation that the legacy
 * migration could not map uniquely. The issue id arrives as a plain
 * string — implicit binding would resolve before EnsureCurrentAccount
 * binds the tenant.
 */
final class AssignLegacyWhatsappConversation
{
    public function __invoke(
        AssignLegacyWhatsappConversationRequest $request,
        CurrentAccount $account,
        string $issue,
        AssignLegacyWhatsappConversationService $service,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        return $responder->respond($service->assign(
            $issue,
            $request->validated('connection_id'),
        ));
    }
}
