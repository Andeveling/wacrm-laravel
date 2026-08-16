<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Inbox\Services\StoreInboxConversationService;
use App\Http\Requests\Inbox\StoreInboxConversationRequest;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreInboxConversation
{
    public function __invoke(StoreInboxConversationRequest $request, CurrentAccount $account, StoreInboxConversationService $service): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        /** @var array{contact_id: string, connection_id?: string|null} $data */
        $data = $request->validated();

        $service->store($data, $request->user()->id);

        return to_route('inbox');
    }
}
