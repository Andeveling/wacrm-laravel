<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Tag;
use App\Support\CurrentAccount;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContacts
{
    public function __invoke(CurrentAccount $account): Response
    {
        return Inertia::render('contacts', [
            'contacts' => Contact::query()
                ->with('tags:id,name,color')
                ->latest('created_at')
                ->get(['id', 'phone', 'name', 'email', 'company', 'avatar_url', 'created_at', 'updated_at']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => CustomField::query()
                ->orderBy('field_name')
                ->get(['id', 'field_name', 'field_type', 'field_options', 'created_at']),
            'canManageCustomFields' => $account->isAdmin(),
        ]);
    }
}
