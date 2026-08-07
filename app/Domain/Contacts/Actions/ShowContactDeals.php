<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Contact;
use App\Models\Deal;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContactDeals
{
    public function __invoke(Contact $contact): Response
    {
        return Inertia::render('contacts', [
            'contactDeals' => Deal::query()
                ->where('contact_id', $contact->id)
                ->with('stage:id,name,color')
                ->latest('created_at')
                ->get()
                ->map(fn (Deal $deal): array => [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'value' => $deal->value,
                    'currency' => $deal->currency,
                    'status' => $deal->status?->value,
                    'stage' => $deal->stage?->only(['id', 'name', 'color']),
                ])
                ->values()
                ->all(),
        ]);
    }
}
