<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;

final class ActiveWhatsappConnectionResolver
{
    public function find(?string $connectionId = null): ?WhatsappPhoneNumberConnection
    {
        $query = WhatsappPhoneNumberConnection::query()
            ->where('readiness', WhatsappConnectionReadiness::Active);

        return is_string($connectionId)
            ? $query->whereKey($connectionId)->first()
            : $query->where('is_default', true)->first();
    }
}
