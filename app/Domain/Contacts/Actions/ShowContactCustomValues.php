<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Contact;
use App\Models\ContactCustomValue;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContactCustomValues
{
    public function __invoke(Contact $contact): Response
    {
        return Inertia::render('contacts', [
            'customValues' => $contact->customValues()
                ->get(['custom_field_id', 'value'])
                ->mapWithKeys(fn (ContactCustomValue $value): array => [
                    $value->custom_field_id => $value->value,
                ])
                ->all(),
        ]);
    }
}
