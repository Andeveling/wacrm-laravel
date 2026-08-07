<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Models\Conversation;
use App\Models\Message;
use Inertia\Inertia;
use Inertia\Response;

final class ShowInbox
{
    public function __invoke(): Response
    {
        $conversations = Conversation::query()
            ->with([
                'contact:id,name,phone,email,company',
                'messages' => fn ($query) => $query->orderBy('created_at')->select([
                    'id',
                    'conversation_id',
                    'sender_type',
                    'content_text',
                    'status',
                    'created_at',
                ]),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'contact_id',
                'status',
                'last_message_text',
                'last_message_at',
                'unread_count',
                'created_at',
                'updated_at',
            ])
            ->values();

        return Inertia::render('inbox', [
            'conversations' => $conversations->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'contact' => $conversation->contact === null ? null : [
                    ...$conversation->contact->only(['id', 'name', 'phone', 'email', 'company']),
                ],
                'status' => $conversation->status->value,
                'last_message_text' => $conversation->last_message_text,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'unread_count' => $conversation->unread_count,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ])->all(),
            'messages' => $conversations
                ->flatMap(function (Conversation $conversation): array {
                    return $conversation->messages->map(fn (Message $message): array => [
                        'id' => $message->id,
                        'conversation_id' => $message->conversation_id,
                        'sender_type' => $message->sender_type,
                        'content_text' => $message->content_text,
                        'status' => $message->status->value,
                        'created_at' => $message->created_at?->toISOString(),
                    ])->values()->all();
                })
                ->values()
                ->all(),
        ]);
    }
}
