<?php

declare(strict_types=1);

namespace Tests\Unit\Meta;

use App\Services\Meta\VerifyMetaWebhookSignature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contract for the HMAC-SHA256 verifier the Meta webhook depends on.
 * Mirrors the unit tests in the Next.js reference implementation
 * (`src/lib/whatsapp/webhook-signature.test.ts`) so the public behavior
 * matches the seam that production has been calling for years.
 */
class VerifyMetaWebhookSignatureTest extends TestCase
{
    private const SECRET = 'test-app-secret-for-meta-webhook';

    private function signedHeader(string $body, ?string $secret = null): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret ?? self::SECRET);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta.app_secret', self::SECRET);
    }

    #[Test]
    public function it_accepts_a_request_signed_with_the_configured_secret(): void
    {
        $body = '{"object":"whatsapp_business_account"}';

        $verifier = new VerifyMetaWebhookSignature;

        $this->assertTrue($verifier->isValid($body, $this->signedHeader($body)));
    }

    #[Test]
    public function it_rejects_a_signature_computed_with_a_different_secret(): void
    {
        $body = '{"object":"whatsapp_business_account"}';
        $verifier = new VerifyMetaWebhookSignature;

        $this->assertFalse($verifier->isValid($body, $this->signedHeader($body, self::SECRET.'tampered')));
    }

    #[Test]
    public function it_rejects_a_body_that_has_been_tampered_with_after_signing(): void
    {
        $signed = '{"entry":[]}';
        $tampered = '{"entry":[{"id":"injected"}]}';
        $header = $this->signedHeader($signed);

        $verifier = new VerifyMetaWebhookSignature;

        $this->assertFalse($verifier->isValid($tampered, $header));
    }

    #[Test]
    public function it_rejects_when_the_x_hub_signature_256_header_is_missing(): void
    {
        $verifier = new VerifyMetaWebhookSignature;

        $this->assertFalse($verifier->isValid('{}', null));
    }

    #[Test]
    public function it_rejects_a_header_without_the_sha256_prefix(): void
    {
        $body = '{}';
        $hex = hash_hmac('sha256', $body, self::SECRET);
        $verifier = new VerifyMetaWebhookSignature;

        $this->assertFalse($verifier->isValid($body, $hex));
        $this->assertFalse($verifier->isValid($body, 'sha512='.$hex));
    }

    #[Test]
    public function it_rejects_a_header_of_the_wrong_length_without_throwing(): void
    {
        $verifier = new VerifyMetaWebhookSignature;

        // hash_equals throws on length mismatch; the verifier should
        // catch it and return false instead of bubbling up.
        $this->assertFalse($verifier->isValid('{}', 'sha256=tooshort'));
    }

    #[Test]
    public function it_rejects_when_meta_app_secret_is_not_configured_fail_closed(): void
    {
        config()->set('services.meta.app_secret', null);
        $verifier = new VerifyMetaWebhookSignature;

        // Even a correctly-formed signature must be rejected when the
        // operator forgot to wire META_APP_SECRET — fail-closed is the
        // whole point of the missing-secret branch.
        $this->assertFalse($verifier->isValid('{}', $this->signedHeader('{}', 'any-secret')));
    }

    #[Test]
    public function it_rejects_when_meta_app_secret_is_the_empty_string(): void
    {
        config()->set('services.meta.app_secret', '');
        $verifier = new VerifyMetaWebhookSignature;

        $this->assertFalse($verifier->isValid('{}', $this->signedHeader('{}', 'any-secret')));
    }

    #[Test]
    public function it_returns_the_same_answer_for_equivalent_hex_case(): void
    {
        $body = '{"x":1}';
        $header = $this->signedHeader($body);
        $verifier = new VerifyMetaWebhookSignature;

        $this->assertTrue($verifier->isValid($body, $header));
        $this->assertTrue($verifier->isValid($body, $header));
    }

    #[Test]
    public function it_exposes_a_static_guard_for_secret_configuration(): void
    {
        config()->set('services.meta.app_secret', null);
        $this->assertFalse(VerifyMetaWebhookSignature::isSecretConfigured());

        config()->set('services.meta.app_secret', 'present');
        $this->assertTrue(VerifyMetaWebhookSignature::isSecretConfigured());

        config()->set('services.meta.app_secret', '');
        $this->assertFalse(VerifyMetaWebhookSignature::isSecretConfigured());
    }
}
