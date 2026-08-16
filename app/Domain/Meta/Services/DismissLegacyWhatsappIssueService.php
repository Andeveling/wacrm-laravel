<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\WhatsappLegacyMigrationIssue;
use Illuminate\Support\Carbon;

final class DismissLegacyWhatsappIssueService
{
    public function dismiss(string $issueId): WhatsappConnectionResult
    {
        $legacyIssue = WhatsappLegacyMigrationIssue::query()
            ->whereKey($issueId)
            ->whereNull('resolved_at')
            ->firstOrFail();

        if (! $legacyIssue->kind->canDismiss()) {
            return WhatsappConnectionResult::failure(
                'Este caso necesita una conexión explícita, no un descarte.',
            );
        }

        $legacyIssue->resolved_at = Carbon::now();
        $legacyIssue->save();

        return WhatsappConnectionResult::success(
            'Caso de migración marcado como atendido.',
        );
    }
}
