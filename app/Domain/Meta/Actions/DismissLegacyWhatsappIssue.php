<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Services\DismissLegacyWhatsappIssueService;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

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
        DismissLegacyWhatsappIssueService $service,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        return $responder->respond($service->dismiss($issue));
    }
}
