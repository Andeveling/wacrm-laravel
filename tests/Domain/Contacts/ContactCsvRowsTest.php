<?php

declare(strict_types=1);

use App\Domain\Contacts\Support\ContactCsvRow;
use App\Domain\Contacts\Support\ContactCsvRows;

covers(ContactCsvRows::class);
mutates(ContactCsvRow::class);

/**
 * The parser is pure: it takes raw CSV text and returns typed rows,
 * touching neither the database nor the container, so every edge case
 * of ADR 0004's Domain suite is verifiable without booting Laravel.
 */
it('returns no rows for an empty file', function (): void {
    expect(ContactCsvRows::parse(''))->toBe([]);
});

it('skips a header row repeated mid-file', function (): void {
    $rows = ContactCsvRows::parse(
        "phone,name\n+57 300 111 2222,Ana\nPhone,Name\n+57 300 333 4444,Beto\n",
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->name)->toBe('Ana')
        ->and($rows[1]->name)->toBe('Beto');
});

it('discards rows wider than the header', function (): void {
    $rows = ContactCsvRows::parse(
        "phone,name\n+57 300 111 2222,Ana,Extra\n+57 300 333 4444,Beto\n",
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->name)->toBe('Beto');
});

it('excludes rows without a phone', function (): void {
    $rows = ContactCsvRows::parse(
        "phone,name\n,Sin Teléfono\n+57 300 333 4444,Beto\n",
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->name)->toBe('Beto');
});

it('normalizes mixed-format phones to digits only', function (): void {
    $rows = ContactCsvRows::parse("phone,name\n+57 (300) 111-2222,Ana\n");

    expect($rows[0]->phone)->toBe('+57 (300) 111-2222')
        ->and($rows[0]->normalizedPhone)->toBe('573001112222');
});

it('splits tags by semicolon, trims them and drops empty ones', function (): void {
    $rows = ContactCsvRows::parse("phone,name,tags\n+57 300 111 2222,Ana, VIP ;; Cliente ;\n");

    expect($rows[0]->tags)->toBe(['VIP', 'Cliente']);
});

it('returns an empty tag list when the column is missing or blank', function (): void {
    $rows = ContactCsvRows::parse("phone,name\n+57 300 111 2222,Ana\n");

    expect($rows[0]->tags)->toBe([]);
});

it('maps blank name email and company to null', function (): void {
    $rows = ContactCsvRows::parse("phone,name,email,company\n+57 300 111 2222,,,\n");

    expect($rows[0])->toBeInstanceOf(ContactCsvRow::class)
        ->and($rows[0]->name)->toBeNull()
        ->and($rows[0]->email)->toBeNull()
        ->and($rows[0]->company)->toBeNull();
});
