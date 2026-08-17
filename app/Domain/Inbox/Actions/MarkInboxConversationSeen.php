<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Models\Conversation;
use App\Support\CurrentAccount;
use Illuminate\Http\Response;

final readonly class MarkInboxConversationSeen
{
    public function __invoke(Conversation $conversation, CurrentAccount $account): Response
    {
        abort_unless($account->isMember(), 403);

        $conversation->update(['unread_count' => 0]);

        return response()->noContent();
    }
}
