<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Http\Requests\Meta\AssignLegacyWhatsappConversationRequest;
use App\Models\Conversation;
use App\Models\WhatsappLegacyMigrationIssue;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

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
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        $legacyIssue = WhatsappLegacyMigrationIssue::query()
            ->whereKey($issue)
            ->whereNull('resolved_at')
            ->firstOrFail();

        if (! $legacyIssue->kind->canAssignConnection() || $legacyIssue->conversation_id === null) {
            return $responder->respond(WhatsappConnectionResult::failure(
                'Este caso no se resuelve eligiendo una conexión.',
            ));
        }

        $conversation = Conversation::query()
            ->whereKey($legacyIssue->conversation_id)
            ->whereNull('connection_id')
            ->firstOrFail();

        $connection = WhatsappPhoneNumberConnection::query()
            ->whereKey($request->validated('connection_id'))
            ->firstOrFail();

        $conversation->connection_id = $connection->id;
        $conversation->save();

        $legacyIssue->resolved_at = Carbon::now();
        $legacyIssue->save();

        return $responder->respond(WhatsappConnectionResult::success(
            'Conversación asignada a la conexión seleccionada.',
        ));
    }
}
