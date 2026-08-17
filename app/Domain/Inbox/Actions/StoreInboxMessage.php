<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Inbox\Services\StoreInboxMessageService;
use App\Http\Requests\Inbox\StoreInboxMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CurrentAccount;
use Illuminate\Http\JsonResponse;

final readonly class StoreInboxMessage
{
    public function __invoke(StoreInboxMessageRequest $request, Conversation $conversation, CurrentAccount $account, StoreInboxMessageService $service): JsonResponse
    {
        abort_unless($account->isMember(), 403);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $message = $service->store($conversation, $request->validated('content_text'), $user->id);

        return response()->json(self::messagePayload($message));
    }

    /**
     * @return array{id: string, conversation_id: string, sender_type: string, content_text: string|null, status: string, created_at: string|null}
     */
    private static function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_type' => $message->sender_type,
            'content_text' => $message->content_text,
            'status' => $message->status->value,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
