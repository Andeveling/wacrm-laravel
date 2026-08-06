<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Contact;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportContacts
{
    public function __invoke(): StreamedResponse
    {
        $contacts = Contact::query()->with('tags')->orderBy('id')->get();

        return response()->streamDownload(function () use ($contacts): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                throw new RuntimeException('No se pudo abrir la salida CSV.');
            }

            fputcsv($handle, ['phone', 'name', 'email', 'company', 'tags']);

            foreach ($contacts as $contact) {
                fputcsv($handle, [
                    $contact->phone,
                    $contact->name,
                    $contact->email,
                    $contact->company,
                    $contact->tags->pluck('name')->implode('; '),
                ]);
            }

            fclose($handle);
        }, 'contacts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
