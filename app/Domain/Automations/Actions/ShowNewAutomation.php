<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use Inertia\Inertia;
use Inertia\Response;

final class ShowNewAutomation
{
    public function __invoke(): Response
    {
        return Inertia::render('automations/new', [
            'connections' => WhatsappPhoneNumberConnection::query()
                ->where('readiness', WhatsappConnectionReadiness::Active)
                ->orderBy('phone_number_id')
                ->get(['id', 'phone_number_id', 'is_default'])
                ->all(),
        ]);
    }
}
