<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\StoreContactNoteRequest;
use App\Models\Contact;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreContactNote
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(StoreContactNoteRequest $request, Contact $contact, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        $contact->notes()->create([
            'user_id' => $request->user()->id,
            'note_text' => $request->validated('note_text'),
        ]);

        return $this->responder->success();
    }
}
