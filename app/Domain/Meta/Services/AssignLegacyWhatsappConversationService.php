<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\Conversation;
use App\Models\WhatsappLegacyMigrationIssue;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Carbon;

final class AssignLegacyWhatsappConversationService
{
    public function assign(string $issueId, string $connectionId): WhatsappConnectionResult
    {
        $legacyIssue = WhatsappLegacyMigrationIssue::query()
            ->whereKey($issueId)
            ->whereNull('resolved_at')
            ->firstOrFail();

        if (! $legacyIssue->kind->canAssignConnection() || $legacyIssue->conversation_id === null) {
            return WhatsappConnectionResult::failure(
                'Este caso no se resuelve eligiendo una conexión.',
            );
        }

        $conversation = Conversation::query()
            ->whereKey($legacyIssue->conversation_id)
            ->whereNull('connection_id')
            ->firstOrFail();

        $connection = WhatsappPhoneNumberConnection::query()
            ->whereKey($connectionId)
            ->firstOrFail();

        $conversation->connection_id = $connection->id;
        $conversation->save();

        $legacyIssue->resolved_at = Carbon::now();
        $legacyIssue->save();

        return WhatsappConnectionResult::success(
            'Conversación asignada a la conexión seleccionada.',
        );
    }
}
