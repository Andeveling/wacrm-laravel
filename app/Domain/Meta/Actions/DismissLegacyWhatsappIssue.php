<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\WhatsappLegacyMigrationIssue;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

/**
 * Close a claimed or incomplete legacy issue after the operator
 * acknowledges that it needs a reconnect rather than a silent map.
 * The issue id arrives as a plain string — implicit binding would
 * resolve before EnsureCurrentAccount binds the tenant.
 */
final class DismissLegacyWhatsappIssue
{
    public function __invoke(
        CurrentAccount $account,
        string $issue,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        $legacyIssue = WhatsappLegacyMigrationIssue::query()
            ->whereKey($issue)
            ->whereNull('resolved_at')
            ->firstOrFail();

        if (! $legacyIssue->kind->canDismiss()) {
            return $responder->respond(WhatsappConnectionResult::failure(
                'Este caso necesita una conexión explícita, no un descarte.',
            ));
        }

        $legacyIssue->resolved_at = Carbon::now();
        $legacyIssue->save();

        return $responder->respond(WhatsappConnectionResult::success(
            'Caso de migración marcado como atendido.',
        ));
    }
}
