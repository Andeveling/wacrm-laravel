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
use Throwable;

final class WhatsappWebhookDeliveryProcessor
{
    public function process(WhatsappWebhookDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $locked = WhatsappWebhookDelivery::query()
                ->whereKey($delivery->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->isSettled()) {
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

            $locked->processed_at = Carbon::now();
            $locked->markSettled();
        });
    }

    public function replay(WhatsappWebhookEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $locked = WhatsappWebhookEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return;
            }

            $outcome = $this->classifyExtracted($this->extractedFromEvent($locked));

            $locked->classification = $outcome['classification'];
            $locked->account_id = $outcome['account_id'];
            $locked->connection_id = $outcome['connection_id'];
            $locked->phone_number_id = $outcome['phone_number_id'];
            $locked->save();

            $this->syncDeliveryOutcome($locked);
            $this->logClassification($locked);
        });
    }

    /**
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function extractEvents(WhatsappWebhookDelivery $delivery): array
    {
        $payload = $delivery->raw_payload;
        $entries = is_array($payload) ? Arr::get($payload, 'entry', []) : null;

        if (! is_array($payload) || ! is_array($entries)) {
            return [$this->malformedEvent($delivery)];
        }

        $events = [];

        foreach ($entries as $entryIndex => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $events = [...$events, ...$this->eventsFromEntry($delivery, $entry, $entryIndex)];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function eventsFromEntry(WhatsappWebhookDelivery $delivery, array $entry, int|string $entryIndex): array
    {
        $changes = Arr::get($entry, 'changes', []);

        if (! is_array($changes)) {
            return [];
        }

        $events = [];

        foreach ($changes as $changeIndex => $change) {
            if (! is_array($change)) {
                continue;
            }

            $events = [...$events, ...$this->eventsFromChange($delivery, $change, $entryIndex, $changeIndex)];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $change
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function eventsFromChange(
        WhatsappWebhookDelivery $delivery,
        array $change,
        int|string $entryIndex,
        int|string $changeIndex,
    ): array {
        $field = is_string($change['field'] ?? null) ? $change['field'] : '';
        $value = is_array($change['value'] ?? null) ? $change['value'] : [];
        $phoneNumberId = $this->phoneNumberIdFromValue($value);

        if ($field !== 'messages') {
            return [$this->unsupportedEvent($delivery, $phoneNumberId, $field, $entryIndex, $changeIndex)];
        }

        return $this->messageFieldEvents($delivery, $value, $phoneNumberId, $field, $entryIndex, $changeIndex);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function phoneNumberIdFromValue(array $value): ?string
    {
        $rawPhoneNumberId = Arr::get($value, 'metadata.phone_number_id');

        return is_string($rawPhoneNumberId) ? $rawPhoneNumberId : null;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function messageFieldEvents(
        WhatsappWebhookDelivery $delivery,
        array $value,
        ?string $phoneNumberId,
        string $field,
        int|string $entryIndex,
        int|string $changeIndex,
    ): array {
        $messages = Arr::get($value, 'messages', []);
        $statuses = Arr::get($value, 'statuses', []);

        if (! $this->hasItems($messages) && ! $this->hasItems($statuses)) {
            return [$this->unsupportedEvent($delivery, $phoneNumberId, $field, $entryIndex, $changeIndex)];
        }

        $contacts = is_array(Arr::get($value, 'contacts')) ? $value['contacts'] : [];

        return [
            ...($this->hasItems($messages)
                ? $this->inboundMessageEvents($delivery, $messages, $contacts, $phoneNumberId, $entryIndex)
                : []),
            ...($this->hasItems($statuses)
                ? $this->statusEvents($delivery, $statuses, $phoneNumberId, $entryIndex)
                : []),
        ];
    }

    private function hasItems(mixed $items): bool
    {
        return is_array($items) && $items !== [];
    }

    /**
     * @return array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}
     */
    private function malformedEvent(WhatsappWebhookDelivery $delivery): array
    {
        return [
            'fingerprint' => 'malformed:'.$delivery->id,
            'phone_number_id' => null,
            'kind' => 'malformed',
            'payload' => [],
        ];
    }

    /**
     * @return array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}
     */
    private function unsupportedEvent(
        WhatsappWebhookDelivery $delivery,
        ?string $phoneNumberId,
        string $field,
        int|string $entryIndex,
        int|string $changeIndex,
    ): array {
        return [
            'fingerprint' => 'unsupported:'.$delivery->id.':'.$entryIndex.':'.$changeIndex,
            'phone_number_id' => $phoneNumberId,
            'kind' => 'unsupported',
            'payload' => ['field' => $field],
        ];
    }

    /**
     * @param  array<mixed>  $messages
     * @param  array<mixed>  $contacts
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function inboundMessageEvents(
        WhatsappWebhookDelivery $delivery,
        array $messages,
        array $contacts,
        ?string $phoneNumberId,
        int|string $entryIndex,
    ): array {
        $events = [];

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

        return $events;
    }

    /**
     * @param  array<mixed>  $statuses
     * @return list<array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}>
     */
    private function statusEvents(
        WhatsappWebhookDelivery $delivery,
        array $statuses,
        ?string $phoneNumberId,
        int|string $entryIndex,
    ): array {
        $events = [];

        foreach ($statuses as $statusIndex => $status) {
            if (! is_array($status)) {
                continue;
            }

            $statusId = is_string($status['id'] ?? null) ? $status['id'] : null;
            $statusValue = is_string($status['status'] ?? null) ? $status['status'] : null;

            $events[] = [
                'fingerprint' => $statusId !== null && $statusValue !== null
                    ? 'status:'.$statusId.':'.$statusValue
                    : 'status:'.$delivery->id.':'.$entryIndex.':'.$statusIndex,
                'phone_number_id' => $phoneNumberId,
                'kind' => 'message_status',
                'payload' => ['status' => $status],
            ];
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

        $outcome = $this->classifyExtracted($extracted);

        $event = $this->recordEvent(
            $delivery,
            $extracted,
            $outcome['classification'],
            accountId: $outcome['account_id'],
            connectionId: $outcome['connection_id'],
            phoneNumberId: $outcome['phone_number_id'],
        );

        $this->logClassification($event);
    }

    /**
     * @return array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}
     */
    private function extractedFromEvent(WhatsappWebhookEvent $event): array
    {
        $kind = match (true) {
            Str::startsWith($event->fingerprint, 'status:') => 'message_status',
            Str::startsWith($event->fingerprint, 'message:') => 'inbound_message',
            default => 'unsupported',
        };

        return [
            'fingerprint' => $event->fingerprint,
            'phone_number_id' => $event->phone_number_id,
            'kind' => $kind,
            'payload' => is_array($event->payload) ? $event->payload : [],
        ];
    }

    /**
     * @param  array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}  $extracted
     * @return array{classification: string, account_id: string|null, connection_id: string|null, phone_number_id: string|null}
     */
    private function classifyExtracted(array $extracted): array
    {
        $phoneNumberId = $extracted['phone_number_id'];

        if ($extracted['kind'] !== 'inbound_message' && $extracted['kind'] !== 'message_status') {
            return $this->classification(
                WhatsappWebhookEvent::CLASSIFICATION_UNSUPPORTED,
                phoneNumberId: is_string($phoneNumberId) ? $phoneNumberId : null,
            );
        }

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            return $this->classification(WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED);
        }

        $connection = WhatsappPhoneNumberConnection::query()
            ->withoutGlobalScope(AccountScope::class)
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($connection === null) {
            return $this->classification(
                WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED,
                phoneNumberId: $phoneNumberId,
            );
        }

        if ($connection->readiness === WhatsappConnectionReadiness::Disconnected) {
            return $this->classification(
                WhatsappWebhookEvent::CLASSIFICATION_BLOCKED,
                $connection->account_id,
                $connection->id,
                $phoneNumberId,
            );
        }

        return $this->classification(
            $this->applyExtractedAgainstConnection($connection, $extracted),
            $connection->account_id,
            $connection->id,
            $phoneNumberId,
        );
    }

    /**
     * @return array{classification: string, account_id: string|null, connection_id: string|null, phone_number_id: string|null}
     */
    private function classification(
        string $classification,
        ?string $accountId = null,
        ?string $connectionId = null,
        ?string $phoneNumberId = null,
    ): array {
        return [
            'classification' => $classification,
            'account_id' => $accountId,
            'connection_id' => $connectionId,
            'phone_number_id' => $phoneNumberId,
        ];
    }

    /**
     * @param  array{fingerprint: string, phone_number_id: string|null, kind: string, payload: array<string, mixed>}  $extracted
     */
    private function applyExtractedAgainstConnection(
        WhatsappPhoneNumberConnection $connection,
        array $extracted,
    ): string {
        app()->instance(AccountScope::CONTAINER_KEY, $connection->account_id);

        try {
            return $extracted['kind'] === 'message_status'
                ? $this->applyStatusUpdate($connection, $extracted['payload'])
                : ($this->applyInboundMessage($connection, $extracted['payload'])
                    ? WhatsappWebhookEvent::CLASSIFICATION_PROCESSED
                    : WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED);
        } catch (Throwable $exception) {
            report($exception);

            return WhatsappWebhookEvent::CLASSIFICATION_FAILED;
        } finally {
            app()->forgetInstance(AccountScope::CONTAINER_KEY);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyInboundMessage(WhatsappPhoneNumberConnection $connection, array $payload): bool
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $contactPayload = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $waId = $this->inboundWaId($contactPayload, $message);

        if ($waId === null) {
            return false;
        }

        $conversation = $this->conversationForInbound($connection, $waId, $contactPayload);
        $this->storeInboundMessage($conversation, $message);
        $this->activateWaitingConnection($connection);

        return true;
    }

    /**
     * @param  array<string, mixed>  $contactPayload
     * @param  array<string, mixed>  $message
     */
    private function inboundWaId(array $contactPayload, array $message): ?string
    {
        $waId = is_string($contactPayload['wa_id'] ?? null)
            ? $contactPayload['wa_id']
            : (is_string($message['from'] ?? null) ? $message['from'] : null);

        return is_string($waId) && $waId !== '' ? $waId : null;
    }

    /**
     * @param  array<string, mixed>  $contactPayload
     */
    private function conversationForInbound(
        WhatsappPhoneNumberConnection $connection,
        string $waId,
        array $contactPayload,
    ): Conversation {
        $normalized = Str::of($waId)->replaceMatches('/\D/', '')->toString();
        $rawName = Arr::get($contactPayload, 'profile.name');
        $name = is_string($rawName) ? $rawName : null;
        $ownerUserId = $this->ownerUserId($connection);

        $contact = Contact::query()->where('phone_normalized', $normalized)->first()
            ?? Contact::query()->create([
                'account_id' => $connection->account_id,
                'user_id' => $ownerUserId,
                'phone' => $waId,
                'name' => $name,
            ]);

        return Conversation::query()
            ->where('contact_id', $contact->id)
            ->where('connection_id', $connection->id)
            ->lockForUpdate()
            ->first()
            ?? Conversation::query()->create([
                'account_id' => $connection->account_id,
                'user_id' => $ownerUserId,
                'contact_id' => $contact->id,
                'connection_id' => $connection->id,
                'status' => ConversationStatus::Open,
            ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function storeInboundMessage(Conversation $conversation, array $message): void
    {
        $rawText = Arr::get($message, 'text.body');
        $text = is_string($rawText) ? $rawText : null;
        $metaMessageId = is_string($message['id'] ?? null) ? $message['id'] : null;
        $alreadyStored = $metaMessageId !== null && Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('message_id', $metaMessageId)
            ->exists();

        if ($alreadyStored) {
            return;
        }

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

    private function activateWaitingConnection(WhatsappPhoneNumberConnection $connection): void
    {
        if ($connection->readiness !== WhatsappConnectionReadiness::WebhookWaiting) {
            return;
        }

        $connection->readiness = WhatsappConnectionReadiness::Active;
        $connection->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyStatusUpdate(WhatsappPhoneNumberConnection $connection, array $payload): string
    {
        $status = is_array($payload['status'] ?? null) ? $payload['status'] : [];
        $metaMessageId = is_string($status['id'] ?? null) ? $status['id'] : null;
        $incoming = MessageStatus::tryFrom(is_string($status['status'] ?? null) ? $status['status'] : '');

        if ($metaMessageId === null || $incoming === null) {
            return WhatsappWebhookEvent::CLASSIFICATION_UNSUPPORTED;
        }

        $message = Message::query()
            ->whereHas('conversation', function ($query) use ($connection): void {
                $query->where('connection_id', $connection->id);
            })
            ->where('message_id', $metaMessageId)
            ->lockForUpdate()
            ->first();

        if ($message === null) {
            return WhatsappWebhookEvent::CLASSIFICATION_UNCORRELATED;
        }

        if ($this->canAdvanceStatus($message->status, $incoming)) {
            $message->status = $incoming;
            $message->save();
        }

        return WhatsappWebhookEvent::CLASSIFICATION_PROCESSED;
    }

    private function canAdvanceStatus(MessageStatus $current, MessageStatus $incoming): bool
    {
        if ($incoming === MessageStatus::Failed) {
            return $current === MessageStatus::Sending || $current === MessageStatus::Sent;
        }

        if ($current === MessageStatus::Failed) {
            return false;
        }

        return $this->statusRank($incoming) > $this->statusRank($current);
    }

    private function statusRank(MessageStatus $status): int
    {
        return match ($status) {
            MessageStatus::Sending => 0,
            MessageStatus::Sent => 1,
            MessageStatus::Delivered => 2,
            MessageStatus::Read => 3,
            MessageStatus::Failed => -1,
        };
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
    ): WhatsappWebhookEvent {
        return WhatsappWebhookEvent::query()->create([
            'delivery_id' => $delivery->id,
            'account_id' => $accountId,
            'connection_id' => $connectionId,
            'phone_number_id' => $phoneNumberId ?? $extracted['phone_number_id'],
            'fingerprint' => $extracted['fingerprint'],
            'classification' => $classification,
            'payload' => $extracted['payload'],
        ]);
    }

    private function syncDeliveryOutcome(WhatsappWebhookEvent $event): void
    {
        $delivery = WhatsappWebhookDelivery::query()
            ->whereKey($event->delivery_id)
            ->lockForUpdate()
            ->first();

        if ($delivery === null || ! $delivery->isSettled()) {
            return;
        }

        $delivery->markSettled();
    }

    private function logClassification(WhatsappWebhookEvent $event): void
    {
        $context = [
            'delivery_id' => $event->delivery_id,
            'event_id' => $event->id,
            'classification' => $event->classification,
        ];

        if ($event->account_id !== null) {
            $context['account_id'] = $event->account_id;
        }

        if ($event->classification === WhatsappWebhookEvent::CLASSIFICATION_FAILED) {
            Log::error('WhatsApp webhook event processing failed.', $context);

            return;
        }

        if ($event->classification === WhatsappWebhookEvent::CLASSIFICATION_PROCESSED) {
            Log::info('WhatsApp webhook event classified.', $context);
        }
    }
}
