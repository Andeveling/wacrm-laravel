<?php

use App\Support\ApiKeyToken;

it('issues a live token with consistent hash and prefix', function () {
    $issued = ApiKeyToken::issue('live');

    expect($issued['plaintext'])->toMatch('/^wacrm_live_[a-f0-9]{64}$/');
    expect($issued['key_prefix'])->toBe(substr($issued['plaintext'], 0, 17));
    expect($issued['key_hash'])->toBe(hash('sha256', $issued['plaintext']));
    expect(strlen($issued['key_hash']))->toBe(64);
});

it('issues a test token', function () {
    $issued = ApiKeyToken::issue('test');

    expect($issued['plaintext'])->toMatch('/^wacrm_test_[a-f0-9]{64}$/');
});

it('rejects unknown environments', function () {
    $this->expectException(InvalidArgumentException::class);

    ApiKeyToken::issue('staging');
});

test('hash is a deterministic sha256 of the plaintext', function () {
    $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

    expect(ApiKeyToken::hash($plaintext))->toBe(hash('sha256', $plaintext));
});

/**
 * @return array<string, array{0: ?string}>
 */
dataset('authorizationHeaderProvider', function () {
    $valid = 'wacrm_live_'.bin2hex(random_bytes(32));

    return [
        'null header' => [null],
        'empty header' => [''],
        'wrong scheme' => ['Basic '.bin2hex(random_bytes(32))],
        'missing bearer' => [$valid],
        'too short random' => ['Bearer wacrm_live_abc'],
        'wrong prefix' => ['Bearer wkrm_live_'.bin2hex(random_bytes(32))],
        'uppercase prefix' => ['Bearer WACRM_LIVE_'.bin2hex(random_bytes(32))],
        'unknown environment' => ['Bearer wacrm_staging_'.bin2hex(random_bytes(32))],
    ];
});

it('rejects malformed authorization headers', function (?string $header) {
    expect(ApiKeyToken::fromAuthorizationHeader($header))->toBeNull();
})->with('authorizationHeaderProvider');

it('accepts a well formed bearer token', function () {
    $plaintext = 'wacrm_test_'.bin2hex(random_bytes(32));

    expect(ApiKeyToken::fromAuthorizationHeader('Bearer '.$plaintext))->toBe($plaintext);
});

it('is case insensitive on the bearer scheme', function () {
    $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

    expect(ApiKeyToken::fromAuthorizationHeader('bearer '.$plaintext))->toBe($plaintext);
});
