<?php

declare(strict_types=1);

namespace App\Services\Meta;

use function hash_equals;

/**
 * Verify the HMAC-SHA256 signature Meta attaches to webhook POSTs.
 *
 * Meta signs the raw request body with the App Secret and sends the result
 * in the `X-Hub-Signature-256: sha256=<hex>` header (Meta reference:
 * https://developers.facebook.com/docs/graph-api/webhooks/getting-started#verify-payloads).
 *
 * Public API:
 *   - `isValid(rawBody, signatureHeader): bool` — single check.
 *   - `isSecretConfigured(): bool` — handy guard for callers that want to
 *     fail fast (controller logs a `meta.webhook.no-secret` event before
 *     the framework reaches the verify step).
 *
 * Fail-closed behavior:
 *   - Missing `services.meta.app_secret` → every request rejected, even if
 *     the signature header is well-formed. Operators who forget to wire
 *     `META_APP_SECRET` to their environment run a fully spoofable webhook;
 *     we refuse to be that channel.
 *   - Header absent, truncated, missing `sha256=` prefix, or computed with
 *     a different secret → rejected.
 */
final class VerifyMetaWebhookSignature
{
    private const HEADER_PREFIX = 'sha256=';

    /**
     * Configuration lookup. Injected rather than reached via `config()`
     * directly so the class is unit-testable without a Laravel container
     * boot, while still allowing production to read the live config.
     */
    public function __construct(
        ?string $secret = null,
    ) {
        $this->secret = $secret ?? (string) config('services.meta.app_secret');
    }

    private readonly string $secret;

    /**
     * Returns true iff the signature header is a well-formed
     * `sha256=` HMAC-SHA256 of `$rawBody` against `META_APP_SECRET`.
     */
    public function isValid(string $rawBody, ?string $signatureHeader): bool
    {
        if (! is_string($signatureHeader) || $signatureHeader === '') {
            return false;
        }

        if (! $this->isSecretConfigured()) {
            return false;
        }

        if (! str_starts_with($signatureHeader, self::HEADER_PREFIX)) {
            return false;
        }

        $expected = self::HEADER_PREFIX.hash_hmac('sha256', $rawBody, (string) $this->secret);
        $provided = $signatureHeader;

        $expectedLen = strlen($expected);
        $providedLen = strlen($provided);

        // Bail on length mismatch — `hash_equals` requires equal-length
        // strings, and a length comparison is itself constant-time for
        // the lengths. Skipping this guard throws "Unknown error" inside
        // hash_equals for short headers.
        if ($expectedLen !== $providedLen) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Whether the App Secret is present and non-empty.
     *
     * Note that we treat the empty string as "unconfigured" because
     * `env('META_APP_SECRET', null)` returns `''` when the variable is
     * explicitly set to nothing — that case should still fail closed.
     */
    public static function isSecretConfigured(): bool
    {
        $secret = config('services.meta.app_secret');

        return is_string($secret) && $secret !== '';
    }
}
