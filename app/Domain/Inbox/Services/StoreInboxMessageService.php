<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Services;

use App\Domain\Inbox\Support\InboxMessageSend;
use App\Domain\Meta\Services\ActiveWhatsappConnectionResolver;
use App\Domain\Meta\Services\MetaGraphClientContract;
use App\Domain\Meta\Support\MetaGraphException;
use App\Models\Conversation;
use App\Models\Enums\MessageStatus;
use App\Models\Message;
use App\Models\WhatsappIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StoreInboxMessageService
{
    public function __construct(
        private ActiveWhatsappConnectionResolver $connections,
        private MetaGraphClientContract $meta,
    ) {}

    public function store(Conversation $conversation, string $content, int $userId): Message
    {
        $send = $this->resolveSend($conversation);

        try {
            $whatsappMessageId = $this->meta->sendTextMessage(
                $send->phoneNumberId,
                $send->token,
                $send->to,
                $content,
            );
        } catch (MetaGraphException $exception) {
            Log::warning('Meta WhatsApp outbound send failed', [
                'operation' => $exception->operation,
                'meta_code' => $exception->metaCode,
                'conversation_id' => $send->conversation->id,
                'connection_id' => $send->connectionId,
            ]);

            throw ValidationException::withMessages(['content_text' => $exception->getMessage()]);
        }

        $message = DB::transaction(function () use ($content, $userId, $send, $whatsappMessageId): Message {
            $message = Message::query()->create([
                'conversation_id' => $send->conversation->id,
                'sender_type' => 'agent',
                'sender_id' => $userId,
                'content_type' => 'text',
                'content_text' => $content,
                'message_id' => $whatsappMessageId,
                'status' => MessageStatus::Sent,
            ]);

            $send->conversation->last_message_text = $content;
            $send->conversation->last_message_at = Carbon::now();
            $send->conversation->save();

            return $message;
        });

        return $message;
    }

    private function resolveSend(Conversation $conversation): InboxMessageSend
    {
        $conversation = Conversation::query()->with('contact')->whereKey($conversation->id)->firstOrFail();
        $connection = $this->connections->find($conversation->connection_id);

        if ($connection === null || ! is_string($connection->phone_number_id) || $connection->phone_number_id === '') {
            throw ValidationException::withMessages([
                'connection_id' => 'Esta conversación no tiene una conexión WhatsApp activa.',
            ]);
        }

        $token = WhatsappIntegration::query()->value('access_token');

        if (! is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'connection_id' => 'Esta conversación no tiene una conexión WhatsApp activa.',
            ]);
        }

        $to = Str::of((string) $conversation->contact?->phone)->replaceMatches('/\D/', '')->toString();

        if ($to === '') {
            throw ValidationException::withMessages([
                'content_text' => 'El contacto no tiene un teléfono válido para WhatsApp.',
            ]);
        }

        return new InboxMessageSend($conversation, $connection->phone_number_id, $token, $to, $connection->id);
    }
}
