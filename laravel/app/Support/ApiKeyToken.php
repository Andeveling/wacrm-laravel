<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Builds and parses the opaque API key tokens a client sends as `Bearer …`.
 *
 * Format: `wacrm_<env>_<random>` where env is `live` (production) or `test`
 * (CI / staging). The `key_prefix` stored in the DB is the first 17 characters
 * of the token — long enough to be unique in the dashboard list, short enough
 * to never be useful as a credential. The full plaintext leaves the system
 * exactly once (returned to the dashboard user at creation); only SHA-256
 * hash sits in the DB after that.
 *
 * Centralizing issue + parse keeps `key_prefix`, `key_hash`, and the guard's
 * regex in one place. If a future migration changes the prefix layout the
 * parser is the single seam to update.
 */
final class ApiKeyToken
{
    private const PREFIX = 'wacrm_';

    private const RANDOM_BYTES = 32;

    /**
     * Length of `key_prefix` stored alongside `key_hash`. Per docs/public-api.md:
     * `wacrm_live_a1b2c3d4` — 11 chars of literal prefix + 6 chars of hex body.
     * Long enough to be unique in dashboard lists, short enough to never serve
     * as a credential (the full plaintext lives in the user's clipboard, not us).
     */
    private const KEY_PREFIX_LENGTH = 17;

    /**
     * Generate a fresh plaintext token and return both the plaintext (shown
     * to the user, never stored) and the prefix + sha256-hash pair (stored).
     *
     * @return array{plaintext: string, key_prefix: string, key_hash: string}
     */
    public static function issue(string $environment = 'live'): array
    {
        if (! in_array($environment, ['live', 'test'], true)) {
            throw new InvalidArgumentException("Unknown API key environment [{$environment}].");
        }

        $random = bin2hex(random_bytes(self::RANDOM_BYTES));

        $plaintext = self::PREFIX.$environment.'_'.$random;
        $keyPrefix = substr($plaintext, 0, self::KEY_PREFIX_LENGTH);

        return [
            'plaintext' => $plaintext,
            'key_prefix' => $keyPrefix,
            'key_hash' => hash('sha256', $plaintext),
        ];
    }

    /**
     * The `live`/`test` environment a stored `key_prefix` was issued for,
     * so callers never sniff the prefix layout themselves.
     */
    public static function environmentFromPrefix(string $keyPrefix): string
    {
        return str_starts_with($keyPrefix, self::PREFIX.'test_') ? 'test' : 'live';
    }

    /**
     * SHA-256 hash the given plaintext token. Use to compare against the
     * `key_hash` stored in the DB during the auth lookup.
     */
    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Parse `Authorization: Bearer <token>` header. Returns the plaintext
     * token if it matches `wacrm_(live|test)_<rest>`; otherwise `null`.
     * The header string is matched case-sensitively against the `wacrm_`
     * prefix — clients normalize their tokens to lowercase already.
     */
    public static function fromAuthorizationHeader(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));

        if (! preg_match('/^wacrm_(live|test)_[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return $token;
    }
}
