<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Facades\DB;

/**
 * Immediately stop routing and strip encrypted tokens for an Account that
 * is being deleted or otherwise decommissioned.
 */
final class DisableWhatsappRoutingForAccount
{
    public function handle(string $accountId): void
    {
        DB::transaction(function () use ($accountId): void {
            WhatsappPhoneNumberConnection::query()
                ->withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->update([
                    'readiness' => WhatsappConnectionReadiness::Disconnected->value,
                    'is_default' => false,
                    'pending_default' => false,
                ]);

            WhatsappIntegration::query()
                ->withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->update([
                    'access_token' => null,
                    'legacy_verify_token' => null,
                ]);
        });
    }
}
