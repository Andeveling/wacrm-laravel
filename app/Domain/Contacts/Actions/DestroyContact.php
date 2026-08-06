<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

final readonly class DestroyContact
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return $this->responder->success();
    }
}
