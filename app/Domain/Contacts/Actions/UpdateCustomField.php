<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class UpdateCustomField
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(UpdateCustomFieldRequest $request, CurrentAccount $account, string $customField): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        CustomField::findOrFail($customField)->update($request->validated());

        return $this->responder->success();
    }
}
