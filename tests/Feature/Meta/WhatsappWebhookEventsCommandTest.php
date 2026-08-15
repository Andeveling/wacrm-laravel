<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\MessageStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Message;
use App\Models\Scopes\AccountScope;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.meta.app_secret', META_WEBHOOK_SECRET);
    config()->set('services.meta.webhook_verify_token', META_WEBHOOK_VERIFY_TOKEN);
});

test('artisan refuses to replay a processed webhook event', function () {
    $delivery = WhatsappWebhookDelivery::factory()->create();
    $event = WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'message:wamid.already-processed',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
    ]);

    expect(Artisan::call('whatsapp:replay-events', ['event' => $event->id]))->toBe(0);
    expect($event->fresh()->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED);
    expect(Artisan::output())->toContain('nothing to replay');
});

test('artisan lists classifiable webhook work and hides processed or unsupported events', function () {
    $delivery = WhatsappWebhookDelivery::factory()->create();

    $unresolved = WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'message:wamid.unresolved-1',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED,
        'phone_number_id' => 'phone-unknown',
    ]);
    $blocked = WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'message:wamid.blocked-1',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_BLOCKED,
        'phone_number_id' => 'phone-old',
    ]);
    $uncorrelated = WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'status:wamid.missing-1:delivered',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_UNCORRELATED,
    ]);
    $failed = WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'message:wamid.failed-1',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_FAILED,
    ]);

    WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'message:wamid.processed-1',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
    ]);
    WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $delivery->id,
        'fingerprint' => 'unsupported:templates',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_UNSUPPORTED,
    ]);

    expect(Artisan::call('whatsapp:list-events'))->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain($unresolved->id)
        ->and($output)->toContain($blocked->id)
        ->and($output)->toContain($uncorrelated->id)
        ->and($output)->toContain($failed->id)
        ->and($output)->not->toContain('wamid.processed-1')
        ->and($output)->not->toContain('unsupported:templates');
});

test('artisan replay applies an unresolved inbound after the connection exists and is a no-op on a second run', function () {
    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-later',
        'wa_id' => '573006660001',
        'name' => 'Pendiente',
        'message_id' => 'wamid.later-1',
        'text' => 'Hola despues',
        'waba_id' => 'waba-later',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $event = WhatsappWebhookEvent::query()->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED);

    [$owner, $account] = memberWithRole('owner');
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'created_by' => $owner->id,
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => 'waba-later',
    ]);
    WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-later',
        'readiness' => WhatsappConnectionReadiness::WebhookWaiting,
    ]);

    expect(Artisan::call('whatsapp:replay-events', ['event' => $event->id]))->toBe(0);

    $event->refresh();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED)
        ->and($event->account_id)->toBe($account->id);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Message::query()->sole()->message_id)->toBe('wamid.later-1')
        ->and(Message::query()->sole()->content_text)->toBe('Hola despues');

    expect(Artisan::call('whatsapp:replay-events', ['event' => $event->id]))->toBe(0);

    expect(Message::query()->count())->toBe(1);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('prune deletes ordinary expired deliveries and keeps pending or failed work', function () {
    $ordinary = WhatsappWebhookDelivery::factory()->create([
        'received_at' => now()->subDays(31),
        'processing_state' => WhatsappWebhookDelivery::STATE_PROCESSED,
        'processed_at' => now()->subDays(31),
    ]);
    WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $ordinary->id,
        'fingerprint' => 'message:wamid.old-processed',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
    ]);

    $pending = WhatsappWebhookDelivery::factory()->create([
        'received_at' => now()->subDays(40),
        'processing_state' => WhatsappWebhookDelivery::STATE_PROCESSED,
        'processed_at' => now()->subDays(40),
    ]);
    WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $pending->id,
        'fingerprint' => 'message:wamid.old-unresolved',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED,
    ]);

    $failed = WhatsappWebhookDelivery::factory()->create([
        'received_at' => now()->subDays(45),
        'processing_state' => WhatsappWebhookDelivery::STATE_PROCESSED,
        'processed_at' => now()->subDays(45),
    ]);
    WhatsappWebhookEvent::factory()->create([
        'delivery_id' => $failed->id,
        'fingerprint' => 'message:wamid.old-failed',
        'classification' => WhatsappWebhookEvent::CLASSIFICATION_FAILED,
    ]);

    $fresh = WhatsappWebhookDelivery::factory()->create([
        'received_at' => now()->subDays(2),
        'processing_state' => WhatsappWebhookDelivery::STATE_PROCESSED,
        'processed_at' => now()->subDays(2),
    ]);

    expect(Artisan::call('whatsapp:prune-deliveries', ['--older-than' => '30days']))->toBe(0);

    expect(WhatsappWebhookDelivery::query()->pluck('id')->all())->toEqualCanonicalizing([
        $pending->id,
        $failed->id,
        $fresh->id,
    ]);
});

test('artisan replay applies a blocked inbound after the connection is reconnected', function () {
    [$account, $owner, $connection] = waitingConnection('phone-old');
    $connection->readiness = WhatsappConnectionReadiness::Disconnected;
    $connection->save();

    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-old',
        'wa_id' => '573004440001',
        'name' => 'Bloqueado',
        'message_id' => 'wamid.blocked-replay',
        'text' => 'Ahora si',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $event = WhatsappWebhookEvent::query()->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_BLOCKED);

    $connection->readiness = WhatsappConnectionReadiness::WebhookWaiting;
    $connection->save();

    expect(Artisan::call('whatsapp:replay-events', ['event' => $event->id]))->toBe(0);

    $event->refresh();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Message::query()->sole()->message_id)->toBe('wamid.blocked-replay');

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('artisan replay correlates an uncorrelated status after the message exists', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.late-status',
        'status' => 'read',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $event = WhatsappWebhookEvent::query()->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_UNCORRELATED);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $contact = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'phone' => '+573001112233',
    ]);
    $conversation = Conversation::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'contact_id' => $contact->id,
        'connection_id' => $connection->id,
    ]);
    $message = Message::factory()->outgoing()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $owner->id,
        'message_id' => 'wamid.late-status',
        'content_text' => 'Hola cliente',
        'status' => MessageStatus::Sent,
    ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    expect(Artisan::call('whatsapp:replay-events', ['event' => $event->id]))->toBe(0);

    $event->refresh();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect($message->fresh()->status)->toBe(MessageStatus::Read);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});
