<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Support\ContactProjection;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Tag;
use App\Support\CurrentAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContacts
{
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function __invoke(CurrentAccount $account, Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $tagIds = array_values(array_filter((array) $request->query('tags', [])));
        $perPage = (int) $request->query('per_page', (string) self::PER_PAGE_OPTIONS[0]);
        $detailId = $request->query('detail') ?? $request->session()->pull('detail_contact_id');

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE_OPTIONS[0];
        }

        $detailContact = is_string($detailId)
            ? Contact::query()->with(ContactProjection::RELATIONS)->find($detailId)
            : null;

        return Inertia::render('contacts', [
            'contacts' => Contact::query()
                ->with(ContactProjection::RELATIONS)
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->when($tagIds !== [], fn ($query) => $query->whereHas(
                    'tags',
                    fn ($query) => $query->whereIn('tags.id', $tagIds),
                ))
                ->latest('created_at')
                ->paginate($perPage, ContactProjection::COLUMNS)
                ->withQueryString()
                ->through(ContactProjection::from(...)),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'customFields' => CustomField::query()
                ->orderBy('field_name')
                ->get(['id', 'field_name', 'field_type', 'field_options', 'created_at']),
            'canManageCustomFields' => $account->isAdmin(),
            'canWrite' => $account->isMember(),
            'filters' => [
                'search' => $search,
                'tags' => $tagIds,
            ],
            'detailContact' => $detailContact !== null
                ? ContactProjection::from($detailContact)
                : null,
        ]);
    }
}
