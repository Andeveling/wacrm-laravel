<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Support;

use App\Models\Contact;
use App\Models\Tag;

/**
 * The public shape of a Contact, defined once for every transport.
 *
 * The web screen selected one set of columns, each MCP tool built its
 * own array and the frontend declared a third shape by hand. They had
 * already drifted — the MCP list omitted the avatar the web screen
 * sent, and tags came back as bare names from one tool, as
 * `{id, name}` from another and as full models from the page. The REST
 * API stub announces a fourth consumer of the same shape.
 *
 * Adding a field to a Contact now means editing this class: the key in
 * {@see from()} and, if it needs a new column, {@see COLUMNS}.
 *
 * The mapping is pure — it reads an already-loaded model and issues no
 * query — so callers must eager-load {@see RELATIONS} or pay for a
 * lazy load per contact.
 */
final class ContactProjection
{
    /**
     * The columns a query must select to satisfy the projection.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'id',
        'phone',
        'name',
        'email',
        'company',
        'avatar_url',
        'created_at',
        'updated_at',
    ];

    /**
     * The relations a query must eager-load, narrowed to the columns
     * the projection reads.
     *
     * @var list<string>
     */
    public const RELATIONS = ['tags:id,name,color'];

    /**
     * @return array<string, mixed>
     */
    public static function from(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'phone' => $contact->phone,
            'name' => $contact->name,
            'email' => $contact->email,
            'company' => $contact->company,
            'avatar_url' => $contact->avatar_url,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
            'tags' => $contact->tags
                ->map(static fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])
                ->values()
                ->all(),
        ];
    }
}
