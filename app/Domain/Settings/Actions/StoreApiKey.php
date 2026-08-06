<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Http\Requests\Settings\StoreApiKeyRequest;
use App\Models\ApiKey;
use App\Support\ApiKeyToken;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

/**
 * Mint an API key for the current account (admin+ only). Only the hash
 * and the prefix are persisted; the plaintext is flashed once so
 * {@see ShowApiKeys} can render it, then it is unrecoverable.
 */
final class StoreApiKey
{
    public function __invoke(StoreApiKeyRequest $request, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $issued = ApiKeyToken::issue();

        ApiKey::create([
            'created_by' => $request->user()->id,
            'name' => $request->validated('name'),
            'key_prefix' => $issued['key_prefix'],
            'key_hash' => $issued['key_hash'],
            'scopes' => $request->validated('scopes') ?? [],
        ]);

        return to_route('settings.api-keys')->with('new_api_key_plaintext', $issued['plaintext']);
    }
}
