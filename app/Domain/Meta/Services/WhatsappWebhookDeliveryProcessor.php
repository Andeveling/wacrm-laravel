<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Models\AccountUser;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\Enums\MessageStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Message;
use App\Models\Scopes\AccountScope;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class WhatsappWebhookDeliveryProcessor
{
    public function process(WhatsappWebhookDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $locked = WhatsappWebhookDelivery::query()
                ->whereKey($delivery->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->processing_state === WhatsappWebhookDelivery::STATE_PROCESSED) {
                return;
            }

            if ($locked->processing_state === WhatsappWebhookDelivery::STATE_RECEIVED) {
                $locked->processing_state = WhatsappWebhookDelivery::STATE_QUEUED;
                $locked->save();
            }

            $extractedEvents = $this->extractEvents($locked);

            if ($extractedEvents === []) {
                $extractedEvents[] = [
                    'fingerprint' => 'empty:'.$locked->id,
                    'phone_number_id' => null,
                    'kind' => 'unsupported',
                    'payload' => [],
                ];
            }

            foreach ($extractedEvents as $extracted) {
                $this->routeExtractedEvent($locked, $extracted);
            }

            $locked->processing_state = WhatsappWebhookDelivery::STATE_PROCESSED;
            $locked->processed_at = Carbon::now();
            $locked->save();
        });
    }

    /**
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function extractEvents(WhatsappWebhookDelivery $delivery): array
    {
        $payload = $delivery->raw_payload;

        if (! is_array($payload)) {
            return [[
                'fingerprint' => 'malformed:'.$delivery->id,
                'phone_number_id' => null,
                'kind' => 'malformed',
                'payload' => [],
            ]];
        }

        $events = [];
        $entries = Arr::get($payload, 'entry', []);

        if (! is_array($entries)) {
            return [[
                'fingerprint' => 'malformed:'.$delivery->id,
                'phone_number_id' => null,
                'kind' => 'malformed',
                'payload' => [],
            ]];
        }

        foreach ($entries as $entryIndex => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $changes = Arr::get($entry, 'changes', []);
            if (! is_array($changes)) {
                continue;
            }

            foreach ($changes as $changeIndex => $change) {
                if (! is_array($change)) {
                    continue;
                }

                $field = is_string($change['field'] ?? null) ? $change['field'] : '';
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $rawPhoneNumberId = Arr::get($value, 'metadata.phone_number_id');
                $phoneNumberId = is_string($rawPhoneNumberId) ? $rawPhoneNumberId : null;

                if ($field !== 'messages') {
                    $events[] = [
                        'fingerprint' => 'unsupported:'.$delivery->id.':'.$entryIndex.':'.$changeIndex,
                        'phone_number_id' => $phoneNumberId,
                        'kind' => 'unsupported',
                        'payload' => ['field' => $field],
                    ];

                    continue;
                }

                $messages = Arr::get($value, 'messages', []);
                if (! is_array($messages) || $messages === []) {
                    $events[] = [
                        'fingerprint' => 'unsupported:'.$delivery->id.':'.$entryIndex.':'.$changeIndex,
                        'phone_number_id' => $phoneNumberId,
                        'kind' => 'unsupported',
                        'payload' => ['field' => $field],
                    ];

                    continue;
                }

                $contacts = is_array(Arr::get($value, 'contacts')) ? $value['contacts'] : [];

                foreach ($messages as $messageIndex => $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $messageId = is_string($message['id'] ?? null) ? $message['id'] : null;
                    $indexedContact = $contacts[$messageIndex] ?? null;
                    $firstContact = $contacts[0] ?? null;
                    $contact = is_array($indexedContact)
                        ? $indexedContact
                        : (is_array($firstContact) ? $firstContact : []);

                    $events[] = [
                        'fingerprint' => $messageId !== null ? 'message:'.$messageId : 'message:'.$delivery->id.':'.$entryIndex.':'.$messageIndex,
                        'phone_number_id' => $phoneNumberId,
                        'kind' => 'inbound_message',
                        'payload' => [
                            'message' => $message,
                            'contact' => $contact,
                        ],
                    ];
                }
            }
        }

        return $events;
    }

    /**
     * @param  array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}  $extracted
     */
    private function routeExtractedEvent(WhatsappWebhookDelivery $delivery, array $extracted): void
    {
        $existing = WhatsappWebhookEvent::query()
            ->where('delivery_id', $delivery->id)
            ->where('fingerprint', $extracted['fingerprint'])
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            return;
        }

        if ($extracted['kind'] !== 'inbound_message') {
            $this->recordEvent($delivery, $extracted, WhatsappWebhookEvent::CLASSIFICATION_UNSUPPORTED);

            return;
        }

        $phoneNumberId = $extracted['phone_number_id'];
        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            $this->recordEvent($delivery, $extracted, WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED);

            return;
        }

        $connection = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScope(AccountScope::class)
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($connection === null) {
            $this->recordEvent($delivery, $extracted, WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED, phoneNumberId: $phoneNumberId);

            return;
        }

        if ($connection->readiness === WhatsappConnectionReadiness::Disconnected) {
            $this->recordEvent(
                $delivery,
                $extracted,
                WhatsappWebhookEvent::CLASSIFICATION_BLOCKED,
                accountId: $connection->account_id,
                connectionId: $connection->id,
                phoneNumberId: $phoneNumberId,
            );

            return;
        }

        app()->instance(AccountScope::CONTAINER_KEY, $connection->account_id);

        try {
            $applied = $this->applyInboundMessage($connection, $extracted['payload']);
        } finally {
            app()->forgetInstance(AccountScope::CONTAINER_KEY);
        }

        if (! $applied) {
            $this->recordEvent(
                $delivery,
                $extracted,
                WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED,
                accountId: $connection->account_id,
                connectionId: $connection->id,
                phoneNumberId: $phoneNumberId,
            );

            return;
        }

        $this->recordEvent(
            $delivery,
            $extracted,
            WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
            accountId: $connection->account_id,
            connectionId: $connection->id,
            phoneNumberId: $phoneNumberId,
        );

        Log::info('WhatsApp inbound message routed.', [
            'delivery_id' => $delivery->id,
            'account_id' => $connection->account_id,
            'connection_id' => $connection->id,
            'processing_state' => WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyInboundMessage(WhatsappPhoneNumberConnection $connection, array $payload): bool
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $contactPayload = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];

        $waId = is_string($contactPayload['wa_id'] ?? null)
            ? $contactPayload['wa_id']
            : (is_string($message['from'] ?? null) ? $message['from'] : null);

        if (! is_string($waId) || $waId === '') {
            return false;
        }

        $normalized = Str::of($waId)->replaceMatches('/\D/', '')->toString();
        $rawName = Arr::get($contactPayload, 'profile.name');
        $name = is_string($rawName) ? $rawName : null;
        $rawText = Arr::get($message, 'text.body');
        $text = is_string($rawText) ? $rawText : null;
        $metaMessageId = is_string($message['id'] ?? null) ? $message['id'] : null;
        $ownerUserId = $this->ownerUserId($connection);

        $contact = Contact::query()->where('phone_normalized', $normalized)->first();

        if ($contact === null) {
            $contact = Contact::query()->create([
                'account_id' => $connection->account_id,
                'user_id' => $ownerUserId,
                'phone' => $waId,
                'name' => $name,
            ]);
        }

        $conversation = Conversation::query()
            ->where('contact_id', $contact->id)
            ->where('connection_id', $connection->id)
            ->lockForUpdate()
            ->first();

        if ($conversation === null) {
            $conversation = Conversation::query()->create([
                'account_id' => $connection->account_id,
                'user_id' => $ownerUserId,
                'contact_id' => $contact->id,
                'connection_id' => $connection->id,
                'status' => ConversationStatus::Open,
            ]);
        }

        $alreadyStored = $metaMessageId !== null && Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('message_id', $metaMessageId)
            ->exists();

        if (! $alreadyStored) {
            Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'customer',
                'content_type' => 'text',
                'content_text' => $text,
                'message_id' => $metaMessageId,
                'status' => MessageStatus::Delivered,
            ]);

            $conversation->last_message_text = $text;
            $conversation->last_message_at = Carbon::now();
            $conversation->unread_count = $conversation->unread_count + 1;
            $conversation->save();
        }

        if ($connection->readiness === WhatsappConnectionReadiness::WebhookWaiting) {
            $connection->readiness = WhatsappConnectionReadiness::Active;
            $connection->save();
        }

        return true;
    }

    private function ownerUserId(WhatsappPhoneNumberConnection $connection): int
    {
        $integrationId = WabaSubscription::query()
            ->whereKey($connection->waba_subscription_id)
            ->value('integration_id');

        $createdBy = is_string($integrationId)
            ? WhatsappIntegration::query()->whereKey($integrationId)->value('created_by')
            : null;

        if (is_numeric($createdBy)) {
            return (int) $createdBy;
        }

        $fallback = AccountUser::query()
            ->where('account_id', $connection->account_id)
            ->orderBy('user_id')
            ->value('user_id');

        if (! is_numeric($fallback)) {
            throw new RuntimeException('A WhatsApp inbound message cannot be stored without an Account member.');
        }

        return (int) $fallback;
    }

    /**
     * @param  array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}  $extracted
     */
    private function recordEvent(
        WhatsappWebhookDelivery $delivery,
        array $extracted,
        string $classification,
        ?string $accountId = null,
        ?string $connectionId = null,
        ?string $phoneNumberId = null,
    ): void {
        WhatsappWebhookEvent::query()->create([
            'delivery_id' => $delivery->id,
            'account_id' => $accountId,
            'connection_id' => $connectionId,
            'phone_number_id' => $phoneNumberId ?? $extracted['phone_number_id'],
            'fingerprint' => $extracted['fingerprint'],
            'classification' => $classification,
            'payload' => $extracted['payload'],
        ]);
    }
}
