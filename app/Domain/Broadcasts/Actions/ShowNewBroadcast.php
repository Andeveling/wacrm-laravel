<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Models\Enums\MessageTemplateStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\MessageTemplate;
use App\Models\Tag;
use App\Models\WhatsappPhoneNumberConnection;
use Inertia\Inertia;
use Inertia\Response;

final class ShowNewBroadcast
{
    public function __invoke(): Response
    {
        return Inertia::render('broadcasts/new', [
            'templates' => MessageTemplate::query()
                ->where('status', MessageTemplateStatus::Approved)
                ->orderBy('name')
                ->get(['id', 'name', 'category', 'language', 'body_text', 'footer_text'])
                ->all(),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color'])->all(),
            'connections' => WhatsappPhoneNumberConnection::query()
                ->where('readiness', WhatsappConnectionReadiness::Active)
                ->orderBy('phone_number_id')
                ->get(['id', 'phone_number_id', 'is_default'])
                ->all(),
        ]);
    }
}
