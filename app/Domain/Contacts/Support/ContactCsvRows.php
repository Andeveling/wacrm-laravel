<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Support;

/**
 * Parses the Contacts import CSV into typed rows.
 *
 * The parsing used to live inside {@see \App\Domain\Contacts\Actions\ImportContacts}'s
 * transaction, so the only way to observe its four rules — skip a
 * repeated header row, discard rows wider than the header, normalize
 * the phone to digits and split tags on ";" — was uploading a file over
 * HTTP against Postgres. This module reads no database, knows no HTTP
 * and is never resolved from the container, so the rules are
 * verifiable in the Domain suite (ADR 0004).
 *
 * A row with no phone is dropped entirely: {@see ImportContacts} keys
 * its dedup lookup on the normalized phone, so such a row could never
 * be persisted anyway.
 */
final class ContactCsvRows
{
    /**
     * @return list<ContactCsvRow>
     */
    public static function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = array_map(static fn (?string $value): string => trim((string) $value), str_getcsv($line, escape: '\\'));
            $normalizedValues = array_map('strtolower', $values);
            $header ??= $normalizedValues;

            if ($normalizedValues === $header) {
                continue;
            }

            if (count($values) > count($header)) {
                continue;
            }

            $data = array_combine($header, array_pad($values, count($header), ''));
            $phone = trim((string) ($data['phone'] ?? ''));
            $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';

            if ($normalizedPhone === '') {
                continue;
            }

            $tags = array_values(array_filter(
                array_map('trim', explode(';', (string) ($data['tags'] ?? ''))),
                static fn (string $tag): bool => $tag !== '',
            ));

            $rows[] = new ContactCsvRow(
                phone: $phone,
                normalizedPhone: $normalizedPhone,
                name: self::nullableValue($data['name'] ?? null),
                email: self::nullableValue($data['email'] ?? null),
                company: self::nullableValue($data['company'] ?? null),
                tags: $tags,
            );
        }

        return $rows;
    }

    private static function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
