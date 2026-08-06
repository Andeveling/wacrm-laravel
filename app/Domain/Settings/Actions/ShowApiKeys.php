<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Models\ApiKey;
use App\Support\CurrentAccount;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → API keys roster. Any member may read it; `canManage` tells
 * the page whether to render the mint/revoke affordances.
 *
 * Keys are scoped to the current account by ApiKey's BelongsToAccount
 * trait (query scope + auto-set account_id on create), so this Action
 * never touches account_id directly.
 *
 * `newKeyPlaintext` is the one-shot flash left behind by
 * {@see StoreApiKey}: the plaintext is never persisted, so this render
 * is the only chance the user has to copy it.
 */
final class ShowApiKeys
{
    public function __invoke(CurrentAccount $account): Response
    {
        return Inertia::render('settings/api-keys', [
            'keys' => ApiKey::query()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'key_prefix', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_at']),
            'canManage' => $account->isAdmin(),
            'newKeyPlaintext' => session('new_api_key_plaintext'),
        ]);
    }
}
