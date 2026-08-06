<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\StoreCustomFieldRequest;
use App\Models\CustomField;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreCustomField
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(StoreCustomFieldRequest $request, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        CustomField::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->responder->success();
    }
}
