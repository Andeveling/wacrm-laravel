<?php

declare(strict_types=1);

namespace Tests\Feature\Meta;

use App\Models\WhatsappWebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end coverage for the Meta WhatsApp webhook ingress (#64):
 * verifies that the HTTP seam behaves the way Meta expects and that
 * every signed delivery is durably persisted before we return 200.
 */
class MetaWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-app-secret-for-meta-webhook';

    private const VERIFY_TOKEN = 'shared-meta-webhook-verify-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta.app_secret', self::SECRET);
        config()->set('services.meta.webhook_verify_token', self::VERIFY_TOKEN);
    }

    private function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, self::SECRET);
    }

    // -----------------------------------------------------------------
    // GET — challenge handshake
    // -----------------------------------------------------------------

    #[Test]
    public function get_returns_the_challenge_in_text_plain_when_token_matches(): void
    {
        $challenge = '1234567890';

        $response = $this->getJson(
            '/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge='.$challenge.'&hub.verify_token='.self::VERIFY_TOKEN,
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame($challenge, $response->getContent());
        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function get_returns_400_when_required_parameters_are_missing(): void
    {
        $this->getJson('/api/whatsapp/webhook?hub.mode=subscribe')
            ->assertBadRequest();

        $this->getJson('/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge=foo')
            ->assertBadRequest();

        $this->getJson('/api/whatsapp/webhook?hub.mode=other&hub.challenge=foo&hub.verify_token='.self::VERIFY_TOKEN)
            ->assertBadRequest();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function get_returns_403_when_verify_token_does_not_match(): void
    {
        $this->getJson(
            '/api/whatsapp/webhook?hub.mode=subscribe&hub.challenge=123&hub.verify_token=wrong',
        )->assertForbidden();
    }

    // -----------------------------------------------------------------
    // POST — happy path
    // -----------------------------------------------------------------

    #[Test]
    public function post_persists_the_delivery_and_returns_200(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
        $header = $this->sign($body);

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
        $this->assertSame($header, $delivery->signature_header);
        $this->assertSame(['object' => 'whatsapp_business_account', 'entry' => []], $delivery->raw_payload);
        $this->assertSame(strlen($body), $delivery->content_length);
        $this->assertSame('received', $delivery->processing_state);
        $this->assertNotNull($delivery->received_at);
        $this->assertNotNull($delivery->processed_at);
    }

    // -----------------------------------------------------------------
    // POST — signature failures
    // -----------------------------------------------------------------

    #[Test]
    public function post_returns_401_when_signature_header_is_missing(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_401_when_signature_is_computed_with_a_wrong_secret(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
        $badHeader = 'sha256='.hash_hmac('sha256', $body, self::SECRET.'tampered');

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $badHeader,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_401_when_body_has_been_tampered_with_after_signing(): void
    {
        $original = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);
        $tampered = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'inject']]], JSON_THROW_ON_ERROR);
        $header = $this->sign($original);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $tampered)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_401_when_signature_uses_a_non_sha256_prefix(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
        $hex = hash_hmac('sha256', $body, self::SECRET);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $hex,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha512='.$hex,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_401_when_signature_header_is_malformed(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=tooshort',
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_401_when_meta_app_secret_is_missing_even_with_a_valid_signature(): void
    {
        config()->set('services.meta.app_secret', null);

        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
        // Sign with the (now-stale) configured secret so we exercise the
        // "signature matches but secret is gone" branch.
        $header = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    // -----------------------------------------------------------------
    // POST — body validation
    // -----------------------------------------------------------------

    #[Test]
    public function post_returns_400_when_body_is_empty_even_with_a_valid_signature(): void
    {
        $header = $this->sign('');

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], '')->assertBadRequest();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_400_when_body_is_not_valid_json(): void
    {
        $body = 'not-json-at-all';
        $header = $this->sign($body);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertBadRequest();

        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    #[Test]
    public function post_returns_413_when_content_length_exceeds_the_limit(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
        $header = $this->sign($body);

        $response = $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CONTENT_LENGTH' => (string) (1_048_576 + 1),
        ], $body);

        $response->assertStatus(413);
        $this->assertDatabaseCount('whatsapp_webhook_deliveries', 0);
    }

    // -----------------------------------------------------------------
    // Persistence
    // -----------------------------------------------------------------

    #[Test]
    public function duplicate_signed_deliveries_each_persist_as_a_new_row_for_idempotency_in_followups(): void
    {
        // Meta may retry the same delivery; the inbox persists every
        // signed attempt. De-duplication of events is the #66 ticket.
        $body = json_encode(['object' => 'whatsapp_business_account'], JSON_THROW_ON_ERROR);
        $header = $this->sign($body);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame(2, WhatsappWebhookDelivery::count());
    }
}
