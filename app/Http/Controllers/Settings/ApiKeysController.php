<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiKeyRequest;
use App\Models\ApiKey;
use App\Support\ApiKeyToken;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → API keys. Any member sees the roster (read-only); admin+ can
 * mint and revoke. Keys are scoped to the current account automatically
 * via ApiKey's BelongsToAccount trait (query scope + auto-set account_id
 * on create), so this controller never touches account_id directly.
 */
class ApiKeysController extends Controller
{
    public function index(CurrentAccount $account): Response
    {
        return Inertia::render('settings/api-keys', [
            'keys' => ApiKey::query()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'key_prefix', 'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_at']),
            'canManage' => $account->isAdmin(),
            'newKeyPlaintext' => session('new_api_key_plaintext'),
        ]);
    }

    public function store(StoreApiKeyRequest $request, CurrentAccount $account): RedirectResponse
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

    /**
     * The route id is resolved manually (not implicit route-model binding):
     * SubstituteBindings runs ahead of `ensure.current-account` in Laravel's
     * fixed middleware priority, so a bound-model lookup would query before
     * the account scope container key exists and 404 on every key,
     * including ones in the caller's own account.
     */
    public function destroy(string $apiKey, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $key = ApiKey::findOrFail($apiKey);
        $key->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Llave «{$key->name}» revocada."]);

        return to_route('settings.api-keys');
    }
}
