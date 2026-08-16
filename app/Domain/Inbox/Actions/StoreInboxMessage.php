<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Inbox\Services\StoreInboxMessageService;
use App\Http\Requests\Inbox\StoreInboxMessageRequest;
use App\Models\Conversation;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

final readonly class StoreInboxMessage
{
    public function __invoke(StoreInboxMessageRequest $request, Conversation $conversation, CurrentAccount $account, StoreInboxMessageService $service): RedirectResponse
    {
        abort_unless($account->isMember(), 403);

        $service->store($conversation, $request->validated('content_text'), $request->user()->id);

        return to_route('inbox');
    }
}
