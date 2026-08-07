<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Models\ContactNote;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class DestroyContactNote
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(ContactNote $note, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        $note->delete();

        return $this->responder->success();
    }
}
