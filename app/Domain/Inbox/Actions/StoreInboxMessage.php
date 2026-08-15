<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Actions;

use App\Domain\Meta\Services\MetaGraphClientContract;
use App\Domain\Meta\Support\MetaGraphException;
use App\Http\Requests\Inbox\StoreInboxMessageRequest;
use App\Models\Conversation;
use App\Models\Enums\MessageStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Message;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StoreInboxMessage
{
    public function __invoke(
        StoreInboxMessageRequest $request,
        Conversation $conversation,
        CurrentAccount $account,
        MetaGraphClientContract $meta,
    ): RedirectResponse {
        abort_unless($account->isMember(), 403);

        $data = $request->validated();
        $send = $this->resolveSend($conversation);

        try {
            $whatsappMessageId = $meta->sendTextMessage(
                $send['phone_number_id'],
                $send['token'],
                $send['to'],
                $data['content_text'],
            );
        } catch (MetaGraphException $exception) {
            Log::warning('Meta WhatsApp outbound send failed', [
                'operation' => $exception->operation,
                'meta_code' => $exception->metaCode,
                'conversation_id' => $send['conversation']->id,
                'connection_id' => $send['connection_id'],
            ]);

            throw ValidationException::withMessages([
                'content_text' => $exception->getMessage(),
            ]);
        }

        DB::transaction(function () use ($data, $request, $send, $whatsappMessageId): void {
            Message::query()->create([
                'conversation_id' => $send['conversation']->id,
                'sender_type' => 'agent',
                'sender_id' => $request->user()->id,
                'content_type' => 'text',
                'content_text' => $data['content_text'],
                'message_id' => $whatsappMessageId,
                'status' => MessageStatus::Sent,
            ]);

            $send['conversation']->last_message_text = $data['content_text'];
            $send['conversation']->last_message_at = Carbon::now();
            $send['conversation']->save();
        });

        return to_route('inbox');
    }

    /**
     * @return array{conversation: Conversation, phone_number_id: string, token: string, to: string, connection_id: string}
     */
    private function resolveSend(Conversation $conversation): array
    {
        $conversation = Conversation::query()
            ->with(['contact', 'whatsappPhoneNumberConnection'])
            ->whereKey($conversation->id)
            ->firstOrFail();

        $connection = $conversation->whatsappPhoneNumberConnection;

        if (! $connection instanceof WhatsappPhoneNumberConnection
            || $connection->readiness !== WhatsappConnectionReadiness::Active
            || ! is_string($connection->phone_number_id)
            || $connection->phone_number_id === '') {
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

        return [
            'conversation' => $conversation,
            'phone_number_id' => $connection->phone_number_id,
            'token' => $token,
            'to' => $to,
            'connection_id' => $connection->id,
        ];
    }
}
