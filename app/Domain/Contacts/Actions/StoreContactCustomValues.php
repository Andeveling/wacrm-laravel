<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Actions;

use App\Domain\Contacts\Responders\ContactRedirectResponder;
use App\Http\Requests\Contacts\StoreContactCustomValuesRequest;
use App\Models\Contact;
use App\Models\ContactCustomValue;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreContactCustomValues
{
    public function __construct(private ContactRedirectResponder $responder) {}

    public function __invoke(StoreContactCustomValuesRequest $request, Contact $contact, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        $rows = collect($request->values())
            ->map(fn (?string $value, string $customFieldId): array => [
                'contact_id' => $contact->id,
                'custom_field_id' => $customFieldId,
                'value' => $value,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            ContactCustomValue::query()->upsert($rows, ['contact_id', 'custom_field_id'], ['value']);
        }

        return $this->responder->success();
    }
}
