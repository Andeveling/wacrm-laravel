<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Domain\Contacts\Support\ContactTags;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

final readonly class StoreContact
{
    public function __construct(
        private ContactRedirectResponder $responder,
        private ContactTags $tags,
    ) {}

    public function __invoke(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::create([
            ...$request->safe()->except('tag_ids'),
            'user_id' => $request->user()->id,
        ]);

        $this->tags->sync($contact, $request->validated('tag_ids', []));

        return $this->responder->success();
    }
}
