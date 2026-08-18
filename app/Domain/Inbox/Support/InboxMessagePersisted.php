<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Support;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * @phpstan-type InboxMessageWire array{
 *     message: array{id: string, conversation_id: string, sender_type: string, content_text: string|null, status: string, created_at: string|null},
 *     conversation: array{
 *         id: string,
 *         contact_id: string,
 *         connection_id: string|null,
 *         contact: array{id: string, name: string|null, phone: string|null, email: string|null, company: string|null}|null,
 *         status: string,
 *         last_message_text: string|null,
 *         last_message_at: string|null,
 *         unread_count: int,
 *         created_at: string|null,
 *         updated_at: string|null
 *     }
 * }
 */
final class InboxMessagePersisted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  InboxMessageWire  $payload
     */
    public function __construct(
        public string $accountId,
        public array $payload,
    ) {}

    public static function fromPersisted(Message $message, Conversation $conversation): self
    {
        return new self($conversation->account_id, self::payloadFor($message, $conversation));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('accounts.'.$this->accountId)];
    }

    public function broadcastAs(): string
    {
        return 'inbox.message';
    }

    /**
     * @return InboxMessageWire
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    /**
     * @return InboxMessageWire
     */
    private static function payloadFor(Message $message, Conversation $conversation): array
    {
        $conversation->loadMissing('contact');

        $contact = $conversation->contact;

        return [
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_type' => $message->sender_type,
                'content_text' => $message->content_text,
                'status' => $message->status->value,
                'created_at' => $message->created_at?->toISOString(),
            ],
            'conversation' => [
                'id' => $conversation->id,
                'contact_id' => $conversation->contact_id,
                'connection_id' => $conversation->connection_id,
                'contact' => $contact === null ? null : [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'company' => $contact->company,
                ],
                'status' => $conversation->status->value,
                'last_message_text' => $conversation->last_message_text,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'unread_count' => $conversation->unread_count,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
            ],
        ];
    }
}
