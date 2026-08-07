<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Domain\Contacts\Support\ContactCsvRow;
use App\Domain\Contacts\Support\ContactCsvRows;
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

        $rows = ContactCsvRows::parse($content);

        DB::transaction(function () use ($rows, $request): void {
            foreach ($rows as $row) {
                $this->persist($row, $request);
            }
        });

        return $this->responder->success();
    }

    private function persist(ContactCsvRow $row, ImportContactsRequest $request): void
    {
        $contact = Contact::query()->where('phone_normalized', $row->normalizedPhone)->first();
        if ($contact === null) {
            $contact = Contact::create([
                'user_id' => $request->user()->id,
                'phone' => $row->phone,
                'name' => $row->name,
                'email' => $row->email,
                'company' => $row->company,
            ]);
        }

        if ($row->tags === []) {
            return;
        }

        $tagIds = collect($row->tags)->map(fn (string $name): string => Tag::firstOrCreate(
            ['name' => $name],
            ['user_id' => $request->user()->id],
        )->id)->all();
        $contact->tags()->syncWithoutDetaching($tagIds);
    }
}
