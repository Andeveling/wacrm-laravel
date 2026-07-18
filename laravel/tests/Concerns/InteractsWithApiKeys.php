<?php

namespace Tests\Concerns;

use App\Models\ApiKey;
use App\Support\ApiKeyToken;

/**
 * Issues a fresh plaintext bearer token that hashes to the same digest as a
 * factory-built `ApiKey` row, so tests can drive the guard without the
 * factory ever persisting the plaintext anywhere.
 */
trait InteractsWithApiKeys
{
    protected function reissuePlaintext(ApiKey $apiKey): string
    {
        $plaintext = 'wacrm_live_'.bin2hex(random_bytes(32));

        $apiKey->forceFill(['key_hash' => ApiKeyToken::hash($plaintext)])->save();

        return $plaintext;
    }
}
