<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Responders;

use Illuminate\Http\RedirectResponse;

final readonly class ContactRedirectResponder
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function success(array $query = []): RedirectResponse
    {
        return to_route('contacts', $query);
    }
}
