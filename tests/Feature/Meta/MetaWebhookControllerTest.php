<?php

declare(strict_types=1);
use App\Models\WhatsappWebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    expect($delivery->raw_payload)->toBe(['object' => 'whatsapp_business_account', 'entry' => []]);
    expect($delivery->content_length)->toBe(strlen($body));
    expect($delivery->processing_state)->toBe('received');
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

    // The decoded array loses original key order — which is the
    // reason raw_body exists.
    expect($delivery->raw_payload)->toBe(['b' => 1, 'a' => 2, 'c' => ['nested' => [1, 2, 3]]]);
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
test('post returns 400 when body is empty even with a valid signature', function () {
    $header = sign('');

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], '')->assertBadRequest();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 400 when body is not valid json', function () {
    $body = 'not-json-at-all';
    $header = sign($body);

    $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertBadRequest();

    $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
});
test('post returns 413 when content length exceeds the limit', function () {
    $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
    $header = sign($body);

    $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
        'HTTP_X_HUB_SIGNATURE_256' => $header,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_CONTENT_LENGTH' => (string) (1_048_576 + 1),
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
