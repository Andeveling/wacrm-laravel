<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\CustomField;
use App\Models\Tag;
use App\Support\CurrentAccount;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContactFields
{
    public function __invoke(CurrentAccount $account): Response
    {
        return Inertia::render('settings/fields', [
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => CustomField::query()
                ->orderBy('field_name')
                ->get(['id', 'field_name', 'field_type', 'field_options', 'created_at']),
            'canManage' => $account->isAdmin(),
        ]);
    }
}
