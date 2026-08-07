<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Contact;
use App\Models\ContactNote;
use Inertia\Inertia;
use Inertia\Response;

final class ShowContactNotes
{
    public function __invoke(Contact $contact): Response
    {
        return Inertia::render('contacts', [
            'notes' => $contact->notes()
                ->with('user:id,name')
                ->latest('created_at')
                ->get()
                ->map(fn (ContactNote $note): array => [
                    'id' => $note->id,
                    'contact_id' => $note->contact_id,
                    'note_text' => $note->note_text,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'user' => $note->user->only(['id', 'name']),
                ])
                ->values()
                ->all(),
        ]);
    }
}
