<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Http\Requests\Contacts\StoreTagRequest;
use App\Models\Tag;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final class StoreTag
{
    public function __invoke(StoreTagRequest $request, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        Tag::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return back();
    }
}
