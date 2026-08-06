<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Responders;

use Illuminate\Http\RedirectResponse;

final readonly class ContactRedirectResponder
{
    public function success(): RedirectResponse
    {
        return to_route('contacts');
    }
}
