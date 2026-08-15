<?php

declare(strict_types=1);

use App\Jobs\ProcessWhatsappWebhookDelivery;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\MessageStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\Message;
use App\Models\Scopes\AccountScope;
use App\Models\User;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappPhoneNumberConnection;
use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

uses(RefreshDatabase::class);

afterEach(function () {
    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

const META_WEBHOOK_SECRET = 'test-app-secret-for-meta-webhook';
const META_WEBHOOK_VERIFY_TOKEN = 'shared-meta-webhook-verify-token';
beforeEach(function () {
    config()->set('services.meta.app_secret', META_WEBHOOK_SECRET);
    config()->set('services.meta.webhook_verify_token', META_WEBHOOK_VERIFY_TOKEN);
});
function sign(string $body): string
{
    return 'sha256='.hash_hmac('sha256', $body, META_WEBHOOK_SECRET);
}

/**
 * @param  list<array{phone_number_id: string, wa_id: string, name: string, message_id: string, text: string, waba_id?: string}>  $messages
 */
function inboundMessagesPayload(array $messages): string
{
    $entries = [];

    foreach ($messages as $message) {
        $wabaId = $message['waba_id'] ?? 'waba-'.$message['phone_number_id'];
        $entries[] = [
            'id' => $wabaId,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'display_phone_number' => $message['wa_id'],
                        'phone_number_id' => $message['phone_number_id'],
                    ],
                    'contacts' => [[
                        'profile' => ['name' => $message['name']],
                        'wa_id' => $message['wa_id'],
                    ]],
                    'messages' => [[
                        'from' => $message['wa_id'],
                        'id' => $message['message_id'],
                        'timestamp' => '1712000000',
                        'type' => 'text',
                        'text' => ['body' => $message['text']],
                    ]],
                ],
            ]],
        ];
    }

    return json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => $entries,
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, string>
 */
function signedWebhookServer(string $body): array
{
    return [
        'HTTP_X_HUB_SIGNATURE_256' => sign($body),
        'CONTENT_TYPE' => 'application/json',
    ];
}

/**
 * @return array{0: Account, 1: User, 2: WhatsappPhoneNumberConnection}
 */
function waitingConnection(string $phoneNumberId, string $wabaId = 'waba-123'): array
{
    [$owner, $account] = memberWithRole('owner');
    $integration = WhatsappIntegration::factory()->for($account)->create([
        'created_by' => $owner->id,
    ]);
    $waba = WabaSubscription::factory()->forIntegration($integration)->create([
        'account_id' => $account->id,
        'waba_id' => $wabaId,
    ]);
    $connection = WhatsappPhoneNumberConnection::factory()->forWaba($waba)->create([
        'account_id' => $account->id,
        'phone_number_id' => $phoneNumberId,
        'readiness' => WhatsappConnectionReadiness::WebhookWaiting,
    ]);

    return [$account, $owner, $connection];
}
test('get returns the challenge in text plain when token matches', function () {
    $challenge = '1234567890';

    $response = $this->getJson(
        '/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge='.$challenge.'&hub.verify_token='.META_WEBHOOK_VERIFY_TOKEN,
    );

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toBe($challenge);
    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('get returns 400 when required parameters are missing', function () {
    $this->getJson('/api/whatsapp/webhook?hub.mode=subscribe')
        ->assertBadRequest();

    $this->getJson('/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge=foo')
        ->assertBadRequest();

    $this->getJson('/api/whatsapp/webhook?hub.mode=other&hub.challenge=foo&hub.verify_token='.META_WEBHOOK_VERIFY_TOKEN)
        ->assertBadRequest();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('get returns 403 when verify token does not match', function () {
    $this->getJson(
        '/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge=123&hub.verify_token=wrong',
    )->assertForbidden();
});
test('post persists the delivery and returns 200', function () {
    $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
    $header = sign($body);

    $response = $this->call(
        method: 'POST',
        uri: '/api/whatsapp/webhook',
        parameters: [],
        cookies: [],
        files: [],
        server: [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );

    $response->assertOk();
    $response->assertJson(['data' => ['state' => 'received']]);

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 1);

    $delivery = WhatsappWebhookDelivery::firstOrFail();
    expect($delivery->signature_header)->toBe($header);

    // raw_body holds the byte-exact body Meta signed — not the
    // json_decode round-trip. Key order, whitespace and number
    // formatting are preserved.
    expect($delivery->raw_body)->toBe($body);

    // raw_payload is jsonb, which Postgres stores key-normalised — key
    // order is not part of its contract, only the key/value pairs are.
    expect($delivery->raw_payload)->toEqual(['object' => 'whatsapp_business_account', 'entry' => []]);
    expect($delivery->content_length)->toBe(strlen($body));
    expect($delivery->processing_state)->toBe(WhatsappWebhookDelivery::STATE_PROCESSED);
    expect($delivery->received_at)->not->toBeNull();
    expect($delivery->processed_at)->not->toBeNull();
});
test('raw body preserves byte exact payload not just decoded array', function () {
    // Use deliberately non-canonical JSON formatting (extra spaces,
    // non-sorted keys) so json_decode normalises things the raw
    // body must keep.
    $body = '{"b":1,"a":2,"c":{"nested":[1,2,3]}}';
    $header = sign($body);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::firstOrFail();
    expect($delivery->raw_body)->toBe($body);

    // The decoded array loses original key order — jsonb doesn't keep
    // it either, which is the reason raw_body exists.
    expect($delivery->raw_payload)->toEqual(['b' => 1, 'a' => 2, 'c' => ['nested' => [1, 2, 3]]]);
});
test('post returns 401 when signature header is missing', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
        ->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 401 when signature is computed with a wrong secret', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $badHeader = 'sha256='.hash_hmac('sha256', $body, META_WEBHOOK_SECRET.'tampered');

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $badHeader,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 401 when body has been tampered with after signing', function () {
    $original = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
    $tampered = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'inject']]], JSON_THROW_ON_ERROR);
    $header = sign($original);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $tampered)->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 401 when signature uses a non sha256 prefix', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $hex = hash_hmac('sha256', $body, META_WEBHOOK_SECRET);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $hex,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha512='.$hex,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 401 when signature header is malformed', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=tooshort',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 401 when meta app secret is missing even with a valid signature', function () {
    config()->set('services.meta.app_secret', null);

    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

    // Sign with the (now-stale) configured secret so we exercise the
    // "signature matches but secret is gone" branch.
    $header = 'sha256='.hash_hmac('sha256', $body, META_WEBHOOK_SECRET);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertUnauthorized();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post retains an empty signed body for asynchronous classification', function () {
    $header = sign('');

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], '')->assertOk();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 1);

    expect(WhatsappWebhookDelivery::firstOrFail()->raw_payload)->toBeNull();
});
test('post retains a signed body that is not valid json for asynchronous classification', function () {
    $body = 'not-json-at-all';
    $header = sign($body);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 1);

    $delivery = WhatsappWebhookDelivery::firstOrFail();
    expect($delivery->raw_body)->toBe($body);
    expect($delivery->raw_payload)->toBeNull();
});
test('post returns 413 when content length exceeds the limit', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $header = sign($body);

    $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CONTENT_LENGTH' => (string) (3_145_728 + 1),
    ], $body);

    $response->assertStatus(413);
    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('duplicate signed deliveries each persist as a new row for idempotency in followups', function () {
    // Meta may retry the same delivery; the inbox persists every
    // signed attempt. De-duplication of events is the #66 ticket.
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $header = sign($body);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect(WhatsappWebhookDelivery::count())->toBe(2);
});

test('post rejects a body over 3 MB using actual bytes even without content length', function () {
    $body = str_repeat('x', 3_145_729);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => sign($body),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(413);

    expect(WhatsappWebhookDelivery::query()->count())->toBe(0);
});

test('post accepts a signed body exactly at the 3 MB limit', function () {
    $prefix = '{"payload":"';
    $suffix = '"}';
    $body = $prefix.str_repeat('a', 3_145_728 - strlen($prefix) - strlen($suffix)).$suffix;

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => sign($body),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::firstOrFail();
    expect($delivery->content_length)->toBe(3_145_728);
    expect($delivery->raw_body)->toBe($body);
});

test('post queues the delivery after the persistence transaction commits', function () {
    Queue::fake();

    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => sign($body),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::firstOrFail();

    Queue::assertPushed(ProcessWhatsappWebhookDelivery::class, function (ProcessWhatsappWebhookDelivery $job) use ($delivery): bool {
        return $job->deliveryId === $delivery->id;
    });

    expect($delivery->processed_at)->toBeNull();
});

test('post returns 503 without logging the signed payload when persistence fails', function () {
    $body = '{"sensitive":"webhook payload"}';

    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new RuntimeException($body));

    Log::shouldReceive('error')
        ->once()
        ->with('Meta webhook delivery could not be persisted.');

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => sign($body),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertServiceUnavailable();
});

test('a routed inbound message creates isolated crm records and activates the waiting connection', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $existing = Contact::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'phone' => '+573001112233',
        'name' => 'Existing Name',
    ]);

    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573001112233',
        'name' => 'Ana Pérez',
        'message_id' => 'wamid.sales-1',
        'text' => 'Hola ventas',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::query()->sole();
    expect($delivery->processing_state)->toBe(WhatsappWebhookDelivery::STATE_PROCESSED)
        ->and($delivery->processed_at)->not->toBeNull();

    $event = WhatsappWebhookEvent::query()->where('delivery_id', $delivery->id)->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED)
        ->and($event->account_id)->toBe($account->id)
        ->and($event->connection_id)->toBe($connection->id);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    $contact = Contact::query()->where('phone_normalized', '573001112233')->sole();
    expect($contact->id)->toBe($existing->id)
        ->and($contact->account_id)->toBe($account->id);

    $conversation = Conversation::query()->sole();
    expect($conversation->account_id)->toBe($account->id)
        ->and($conversation->contact_id)->toBe($contact->id)
        ->and($conversation->connection_id)->toBe($connection->id)
        ->and($conversation->last_message_text)->toBe('Hola ventas');

    $message = Message::query()->sole();
    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->message_id)->toBe('wamid.sales-1')
        ->and($message->content_text)->toBe('Hola ventas')
        ->and($message->sender_type)->toBe('customer');

    expect($connection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a classified inbound logs delivery and event identifiers after routing', function () {
    [$account] = waitingConnection('phone-sales');

    /** @var list<MessageLogged> $logged */
    $logged = [];
    Log::listen(function (MessageLogged $entry) use (&$logged): void {
        $logged[] = $entry;
    });

    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573001112233',
        'name' => 'Ana Pérez',
        'message_id' => 'wamid.log-1',
        'text' => 'Hola logs',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::query()->sole();
    $event = WhatsappWebhookEvent::query()->sole();
    $classified = collect($logged)->first(
        fn (MessageLogged $entry): bool => $entry->message === 'WhatsApp webhook event classified.',
    );

    expect($classified)->not->toBeNull()
        ->and($classified->context['delivery_id'])->toBe($delivery->id)
        ->and($classified->context['event_id'])->toBe($event->id)
        ->and($classified->context['account_id'])->toBe($account->id)
        ->and($classified->context['classification'])->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED)
        ->and($classified->context)->not->toHaveKey('raw_body');
});

test('a delivery with two connections isolates tenants and does not leak crm records', function () {
    [$salesAccount, $salesOwner, $salesConnection] = waitingConnection('phone-sales', 'waba-sales');
    [$supportAccount, $supportOwner, $supportConnection] = waitingConnection('phone-support', 'waba-support');

    Contact::factory()->create([
        'account_id' => $salesAccount->id,
        'user_id' => $salesOwner->id,
        'phone' => '573009990001',
        'name' => 'Cliente ventas',
    ]);

    $body = inboundMessagesPayload([
        [
            'phone_number_id' => 'phone-sales',
            'wa_id' => '573009990001',
            'name' => 'Cliente ventas',
            'message_id' => 'wamid.sales-2',
            'text' => 'Quiero precio',
            'waba_id' => 'waba-sales',
        ],
        [
            'phone_number_id' => 'phone-support',
            'wa_id' => '573009990002',
            'name' => 'Cliente soporte',
            'message_id' => 'wamid.support-1',
            'text' => 'Necesito ayuda',
            'waba_id' => 'waba-support',
        ],
    ]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::query()->latest('received_at')->firstOrFail();
    expect($delivery->processing_state)->toBe(WhatsappWebhookDelivery::STATE_PROCESSED);

    $events = WhatsappWebhookEvent::query()->where('delivery_id', $delivery->id)->orderBy('fingerprint')->get();
    expect($events)->toHaveCount(2)
        ->and($events->pluck('account_id')->all())->toEqualCanonicalizing([
            $salesAccount->id,
            $supportAccount->id,
        ]);

    app()->instance(AccountScope::CONTAINER_KEY, $salesAccount->id);
    $salesConversation = Conversation::query()->sole();
    expect(Contact::query()->count())->toBe(1)
        ->and($salesConversation->connection_id)->toBe($salesConnection->id)
        ->and($salesConversation->messages()->sole()->message_id)->toBe('wamid.sales-2')
        ->and($salesConversation->messages()->sole()->content_text)->toBe('Quiero precio');

    app()->instance(AccountScope::CONTAINER_KEY, $supportAccount->id);
    $supportConversation = Conversation::query()->sole();
    expect(Contact::query()->count())->toBe(1)
        ->and($supportConversation->connection_id)->toBe($supportConnection->id)
        ->and(Contact::query()->sole()->phone_normalized)->toBe('573009990002')
        ->and($supportConversation->messages()->sole()->message_id)->toBe('wamid.support-1');

    expect($salesConnection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active)
        ->and($supportConnection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('unknown disconnected and unsupported events are classified without mutating crm state', function () {
    [$account, $owner, $disconnected] = waitingConnection('phone-old', 'waba-old');
    $disconnected->readiness = WhatsappConnectionReadiness::Disconnected;
    $disconnected->save();

    $body = json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => 'waba-unknown',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'phone-unknown'],
                        'contacts' => [['profile' => ['name' => 'Ghost'], 'wa_id' => '573000000001']],
                        'messages' => [[
                            'from' => '573000000001',
                            'id' => 'wamid.unknown-1',
                            'timestamp' => '1712000000',
                            'type' => 'text',
                            'text' => ['body' => 'Hola fantasma'],
                        ]],
                    ],
                ]],
            ],
            [
                'id' => 'waba-old',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'phone-old'],
                        'contacts' => [['profile' => ['name' => 'Viejo'], 'wa_id' => '573000000002']],
                        'messages' => [[
                            'from' => '573000000002',
                            'id' => 'wamid.blocked-1',
                            'timestamp' => '1712000000',
                            'type' => 'text',
                            'text' => ['body' => 'No deberia entrar'],
                        ]],
                    ],
                ]],
            ],
            [
                'id' => 'waba-old',
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => ['event' => 'APPROVED'],
                ]],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::query()->latest('received_at')->firstOrFail();
    $classifications = WhatsappWebhookEvent::query()
        ->where('delivery_id', $delivery->id)
        ->pluck('classification')
        ->all();

    expect($classifications)->toEqualCanonicalizing([
        WhatsappWebhookEvent::CLASSIFICATION_UNRESOLVED,
        WhatsappWebhookEvent::CLASSIFICATION_BLOCKED,
        WhatsappWebhookEvent::CLASSIFICATION_UNSUPPORTED,
    ]);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    expect(Contact::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and($disconnected->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Disconnected);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('the same wa_id opens separate conversations per connection inside one account', function () {
    [$account, $owner, $sales] = waitingConnection('phone-sales', 'waba-multi');
    $supportWaba = WabaSubscription::query()->withoutGlobalScopes()->where('account_id', $account->id)->sole();
    $support = WhatsappPhoneNumberConnection::factory()->forWaba($supportWaba)->create([
        'account_id' => $account->id,
        'phone_number_id' => 'phone-support',
        'readiness' => WhatsappConnectionReadiness::WebhookWaiting,
    ]);

    $body = inboundMessagesPayload([
        [
            'phone_number_id' => 'phone-sales',
            'wa_id' => '573008880001',
            'name' => 'Mismo cliente',
            'message_id' => 'wamid.same-sales',
            'text' => 'Hola ventas',
            'waba_id' => 'waba-multi',
        ],
        [
            'phone_number_id' => 'phone-support',
            'wa_id' => '573008880001',
            'name' => 'Mismo cliente',
            'message_id' => 'wamid.same-support',
            'text' => 'Hola soporte',
            'waba_id' => 'waba-multi',
        ],
    ]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(2)
        ->and(Conversation::query()->pluck('connection_id')->all())->toEqualCanonicalizing([
            $sales->id,
            $support->id,
        ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a retried inbound message does not duplicate crm records and still activates the connection', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');

    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573007770001',
        'name' => 'Retry',
        'message_id' => 'wamid.retry-1',
        'text' => 'Primera entrega',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();
    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    expect(WhatsappWebhookDelivery::query()->count())->toBe(2);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->sole()->messages()->count())->toBe(1)
        ->and($connection->fresh()->readiness)->toBe(WhatsappConnectionReadiness::Active);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

/**
 * @param  list<array{phone_number_id: string, message_id: string, status: string, recipient_id?: string, waba_id?: string}>  $statuses
 */
function inboundStatusesPayload(array $statuses): string
{
    $entries = [];

    foreach ($statuses as $status) {
        $wabaId = $status['waba_id'] ?? 'waba-'.$status['phone_number_id'];
        $entries[] = [
            'id' => $wabaId,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'display_phone_number' => $status['recipient_id'] ?? '573001112233',
                        'phone_number_id' => $status['phone_number_id'],
                    ],
                    'statuses' => [[
                        'id' => $status['message_id'],
                        'status' => $status['status'],
                        'timestamp' => '1712000100',
                        'recipient_id' => $status['recipient_id'] ?? '573001112233',
                    ]],
                ],
            ]],
        ];
    }

    return json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => $entries,
    ], JSON_THROW_ON_ERROR);
}

test('a late sent status does not regress a delivered outbound message', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

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
    $message = Message::factory()->outgoing()->delivered()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $owner->id,
        'message_id' => 'wamid.out-1',
        'content_text' => 'Hola cliente',
    ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.out-1',
        'status' => 'sent',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and(Message::query()->count())->toBe(1);

    $event = WhatsappWebhookEvent::query()->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED)
        ->and($event->account_id)->toBe($account->id)
        ->and($event->connection_id)->toBe($connection->id);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a status for an unknown message id is uncorrelated and does not invent a message', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.missing-1',
        'status' => 'delivered',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $event = WhatsappWebhookEvent::query()->sole();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_UNCORRELATED)
        ->and($event->account_id)->toBe($account->id)
        ->and($event->connection_id)->toBe($connection->id);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Message::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(Contact::query()->count())->toBe(0);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a delivered status advances a sent outbound message', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

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
        'message_id' => 'wamid.out-2',
        'content_text' => 'Hola cliente',
        'status' => MessageStatus::Sent,
    ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.out-2',
        'status' => 'delivered',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a failed status does not regress a delivered outbound message', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

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
    $message = Message::factory()->outgoing()->delivered()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $owner->id,
        'message_id' => 'wamid.out-3',
        'content_text' => 'Hola cliente',
    ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.out-3',
        'status' => 'failed',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('a failed status marks a sent outbound message as failed', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');
    $connection->readiness = WhatsappConnectionReadiness::Active;
    $connection->save();

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
        'message_id' => 'wamid.out-4',
        'content_text' => 'Hola cliente',
        'status' => MessageStatus::Sent,
    ]);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $body = inboundStatusesPayload([[
        'phone_number_id' => 'phone-sales',
        'message_id' => 'wamid.out-4',
        'status' => 'failed',
        'recipient_id' => '573001112233',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect($message->fresh()->status)->toBe(MessageStatus::Failed);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('the same inbound message in a second delivery does not duplicate crm records', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');

    $first = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573007770002',
        'name' => 'Retry',
        'message_id' => 'wamid.retry-2',
        'text' => 'Primera entrega',
        'waba_id' => 'waba-123',
    ]]);
    $second = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573007770002',
        'name' => 'Retry',
        'message_id' => 'wamid.retry-2',
        'text' => 'Segunda entrega',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($first), $first)->assertOk();
    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($second), $second)->assertOk();

    expect(WhatsappWebhookDelivery::query()->count())->toBe(2)
        ->and(WhatsappWebhookEvent::query()->count())->toBe(2);

    $classifications = WhatsappWebhookEvent::query()->pluck('classification')->all();
    expect($classifications)->toEqualCanonicalizing([
        WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
        WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
    ]);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(1)
        ->and(Message::query()->sole()->content_text)->toBe('Primera entrega');

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});

test('an inbound that exhausts internal processing is failed and replayable without inventing crm records', function () {
    [$account, $owner, $connection] = waitingConnection('phone-sales');

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);
    WhatsappIntegration::query()->update(['created_by' => null]);
    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $account->users()->detach();

    $body = inboundMessagesPayload([[
        'phone_number_id' => 'phone-sales',
        'wa_id' => '573005550001',
        'name' => 'Sin miembro',
        'message_id' => 'wamid.failed-internal',
        'text' => 'No deberia persistir',
        'waba_id' => 'waba-123',
    ]]);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], signedWebhookServer($body), $body)->assertOk();

    $delivery = WhatsappWebhookDelivery::query()->sole();
    $event = WhatsappWebhookEvent::query()->sole();

    expect($delivery->processing_state)->toBe(WhatsappWebhookDelivery::STATE_FAILED)
        ->and($delivery->processed_at)->not->toBeNull()
        ->and($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_FAILED)
        ->and($event->account_id)->toBe($account->id)
        ->and($event->connection_id)->toBe($connection->id);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Contact::query()->count())->toBe(0)
        ->and(Message::query()->count())->toBe(0);

    app()->forgetInstance(AccountScope::CONTAINER_KEY);

    $account->users()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

    expect(Artisan::call('whatsapp:replay-events', ['target' => $event->id]))->toBe(0);

    $event->refresh();
    $delivery->refresh();
    expect($event->classification)->toBe(WhatsappWebhookEvent::CLASSIFICATION_PROCESSED)
        ->and($delivery->processing_state)->toBe(WhatsappWebhookDelivery::STATE_PROCESSED);

    app()->instance(AccountScope::CONTAINER_KEY, $account->id);

    expect(Message::query()->sole()->message_id)->toBe('wamid.failed-internal');

    app()->forgetInstance(AccountScope::CONTAINER_KEY);
});
