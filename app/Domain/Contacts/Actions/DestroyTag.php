<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Models\Tag;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final class DestroyTag
{
    public function __invoke(CurrentAccount $account, string $tag): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        Tag::findOrFail($tag)->delete();

        return back();
    }
}
