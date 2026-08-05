<?php

declare(strict_types=1);
use App\Services\Meta\VerifyMetaWebhookSignature;

const META_SIGNATURE_SECRET = 'test-app-secret-for-meta-webhook';
function signedHeader(string $body, ?string $secret = null): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret ?? META_SIGNATURE_SECRET);
}
beforeEach(function () {
    config()->set('services.meta.app_secret', META_SIGNATURE_SECRET);
});
it('accepts a request signed with the configured secret', function () {
    $body = '{"object":"whatsapp_business_account"}';

    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid($body, signedHeader($body)))->toBeTrue();
});
it('rejects a signature computed with a different secret', function () {
    $body = '{"object":"whatsapp_business_account"}';
    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid($body, signedHeader($body, META_SIGNATURE_SECRET.'tampered')))->toBeFalse();
});
it('rejects a body that has been tampered with after signing', function () {
    $signed = '{"entry":[]}';
    $tampered = '{"entry":[{"id":"injected"}]}';
    $header = signedHeader($signed);

    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid($tampered, $header))->toBeFalse();
});
it('rejects when the x hub signature 256 header is missing', function () {
    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid('{}', null))->toBeFalse();
});
it('rejects a header without the sha256 prefix', function () {
    $body = '{}';
    $hex = hash_hmac('sha256', $body, META_SIGNATURE_SECRET);
    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid($body, $hex))->toBeFalse();
    expect($verifier->isValid($body, 'sha512='.$hex))->toBeFalse();
});
it('rejects a header of the wrong length without throwing', function () {
    $verifier = new VerifyMetaWebhookSignature;

    // hash_equals throws on length mismatch; the verifier should
    // catch it and return false instead of bubbling up.
    expect($verifier->isValid('{}', 'sha256=tooshort'))->toBeFalse();
});
it('rejects when meta app secret is not configured fail closed', function () {
    config()->set('services.meta.app_secret', null);
    $verifier = new VerifyMetaWebhookSignature;

    // Even a correctly-formed signature must be rejected when the
    // operator forgot to wire META_APP_SECRET — fail-closed is the
    // whole point of the missing-secret branch.
    expect($verifier->isValid('{}', signedHeader('{}', 'any-secret')))->toBeFalse();
});
it('rejects when meta app secret is the empty string', function () {
    config()->set('services.meta.app_secret', '');
    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid('{}', signedHeader('{}', 'any-secret')))->toBeFalse();
});
it('returns the same answer for equivalent hex case', function () {
    $body = '{"x":1}';
    $header = signedHeader($body);
    $verifier = new VerifyMetaWebhookSignature;

    expect($verifier->isValid($body, $header))->toBeTrue();
    expect($verifier->isValid($body, $header))->toBeTrue();
});
it('exposes a static guard for secret configuration', function () {
    config()->set('services.meta.app_secret', null);
    expect(VerifyMetaWebhookSignature::isSecretConfigured())->toBeFalse();

    config()->set('services.meta.app_secret', 'present');
    expect(VerifyMetaWebhookSignature::isSecretConfigured())->toBeTrue();

    config()->set('services.meta.app_secret', '');
    expect(VerifyMetaWebhookSignature::isSecretConfigured())->toBeFalse();
});
