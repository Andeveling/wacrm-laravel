<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\BulkDestroyContactsRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

final readonly class BulkDestroyContacts
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(BulkDestroyContactsRequest $request): RedirectResponse
    {
        Contact::query()->whereKey($request->validated('ids'))->get()->each->delete();

        return $this->responder->success();
    }
}
