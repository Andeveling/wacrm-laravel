<?php

declare(strict_types=1);

use App\Domain\Contacts\Support\ContactProjection;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Support\Carbon;

covers(ContactProjection::class);

/**
 * The projection is pure: it reads an already-loaded model and issues
 * no query, so it is verifiable without a database, without the MCP
 * server and without rendering a page. The models here are built in
 * memory and their relations set by hand — touching the database would
 * mean the mapping had grown a query it should not have.
 *
 * Attributes go in raw, and the timestamps go in as Carbon: with no
 * Laravel booted there is no connection to ask for a date format, and
 * Eloquent only skips that lookup for a value that is already a date.
 */
function contactFixture(array $attributes = [], array $tags = []): Contact
{
    $contact = (new Contact)->setRawAttributes([
        'id' => '01900000-0000-7000-8000-000000000001',
        'phone' => '+573001112222',
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'company' => 'Acme SAS',
        'avatar_url' => 'https://cdn.example.com/ana.png',
        'created_at' => Carbon::parse('2026-01-01T10:00:00Z'),
        'updated_at' => Carbon::parse('2026-01-02T10:00:00Z'),
        ...$attributes,
    ]);

    return $contact->setRelation('tags', collect($tags));
}

it('emits the public shape in a stable key order', function (): void {
    $projected = ContactProjection::from(contactFixture());

    expect(array_keys($projected))->toBe([
        'id',
        'phone',
        'name',
        'email',
        'company',
        'avatar_url',
        'created_at',
        'updated_at',
        'tags',
    ]);
});

it('maps every field of a fully populated Contact', function (): void {
    $tag = (new Tag)->setRawAttributes([
        'id' => '01900000-0000-7000-8000-0000000000aa',
        'name' => 'VIP',
        'color' => '#f59e0b',
    ]);

    $projected = ContactProjection::from(contactFixture(tags: [$tag]));

    expect($projected)->toBe([
        'id' => '01900000-0000-7000-8000-000000000001',
        'phone' => '+573001112222',
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'company' => 'Acme SAS',
        'avatar_url' => 'https://cdn.example.com/ana.png',
        'created_at' => '2026-01-01T10:00:00+00:00',
        'updated_at' => '2026-01-02T10:00:00+00:00',
        'tags' => [[
            'id' => '01900000-0000-7000-8000-0000000000aa',
            'name' => 'VIP',
            'color' => '#f59e0b',
        ]],
    ]);
});

it('emits null rather than dropping the optional fields', function (): void {
    $projected = ContactProjection::from(contactFixture([
        'name' => null,
        'email' => null,
        'company' => null,
        'avatar_url' => null,
        'created_at' => null,
        'updated_at' => null,
    ]));

    expect($projected['name'])->toBeNull()
        ->and($projected['email'])->toBeNull()
        ->and($projected['company'])->toBeNull()
        ->and($projected['avatar_url'])->toBeNull()
        ->and($projected['created_at'])->toBeNull()
        ->and($projected['updated_at'])->toBeNull()
        ->and($projected['tags'])->toBe([]);
});

it('lists a column for every field the projection reads from the table', function (): void {
    // tags is the only key backed by a relation instead of a column, so
    // a field added to `from()` without a column shows up here.
    expect(ContactProjection::COLUMNS)
        ->toBe(array_values(array_diff(array_keys(ContactProjection::from(contactFixture())), ['tags'])));
});
