<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Models\MessageTemplate;
use Inertia\Inertia;
use Inertia\Response;

final class ShowMessageTemplates
{
    public function __invoke(): Response
    {
        $templates = MessageTemplate::query()
            ->latest('created_at')
            ->get([
                'id',
                'name',
                'category',
                'language',
                'body_text',
                'footer_text',
                'status',
                'rejection_reason',
            ])
            ->map(fn (MessageTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'category' => $template->category,
                'language' => $template->language,
                'body_text' => $template->body_text,
                'footer_text' => $template->footer_text,
                'status' => $template->status?->value,
                'rejection_reason' => $template->rejection_reason,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/templates', ['templates' => $templates]);
    }
}
