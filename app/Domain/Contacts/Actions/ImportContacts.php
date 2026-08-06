<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\ImportContactsRequest;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final readonly class ImportContacts
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(ImportContactsRequest $request): RedirectResponse
    {
        $content = $request->file('file')->get();
        abort_if($content === false, 422, 'No se pudo leer el archivo.');

        $rows = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $header = null;

        DB::transaction(function () use ($rows, &$header, $request): void {
            foreach ($rows as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $values = array_map(static fn (?string $value): string => trim((string) $value), str_getcsv($line));
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

                $contact = Contact::query()->where('phone_normalized', $normalizedPhone)->first();
                if ($contact === null) {
                    $contact = Contact::create([
                        'user_id' => $request->user()->id,
                        'phone' => $phone,
                        'name' => self::nullableValue($data['name'] ?? null),
                        'email' => self::nullableValue($data['email'] ?? null),
                        'company' => self::nullableValue($data['company'] ?? null),
                    ]);
                }

                $tagNames = array_filter(array_map('trim', explode(';', (string) ($data['tags'] ?? ''))));
                if ($tagNames === []) {
                    continue;
                }

                $tagIds = collect($tagNames)->map(fn (string $name): string => Tag::firstOrCreate(
                    ['name' => $name],
                    ['user_id' => $request->user()->id],
                )->id)->all();
                $contact->tags()->syncWithoutDetaching($tagIds);
            }
        });

        return $this->responder->success();
    }

    private static function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
