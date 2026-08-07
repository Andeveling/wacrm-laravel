<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Http\Requests\Contacts\UpdateTagRequest;
use App\Models\Tag;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final class UpdateTag
{
    public function __invoke(UpdateTagRequest $request, CurrentAccount $account, string $tag): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        Tag::findOrFail($tag)->update($request->validated());

        return back();
    }
}
