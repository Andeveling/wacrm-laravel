<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Collection;

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

    /**
     * @return Collection<int, WhatsappPhoneNumberConnection>
     */
    public function list(): Collection
    {
        return WhatsappPhoneNumberConnection::query()
            ->where('readiness', WhatsappConnectionReadiness::Active)
            ->orderBy('phone_number_id')
            ->get(['id', 'phone_number_id', 'is_default']);
    }
}
