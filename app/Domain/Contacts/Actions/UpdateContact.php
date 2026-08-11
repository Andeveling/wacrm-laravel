<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Domain\Contacts\Support\ContactTags;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

final readonly class UpdateContact
{
    public function __construct(
        private ContactRedirectResponder $responder,
        private ContactTags $tags,
    ) {}

    public function __invoke(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->safe()->except('tag_ids'));

        if ($request->has('tag_ids')) {
            $this->tags->sync($contact, $request->validated('tag_ids'));
        }

        return $this->responder->success()->with('detail_contact_id', $contact->id);
    }
}
