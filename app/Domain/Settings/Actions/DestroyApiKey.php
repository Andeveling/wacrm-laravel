<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Models\ApiKey;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Revoke an API key (admin+ only).
 *
 * The route id is resolved manually (not implicit route-model binding):
 * SubstituteBindings runs ahead of `ensure.current-account` in Laravel's
 * fixed middleware priority, so a bound-model lookup would query before
 * the account scope container key exists and 404 on every key,
 * including ones in the caller's own account.
 */
final class DestroyApiKey
{
    public function __invoke(string $apiKey, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $key = ApiKey::findOrFail($apiKey);
        $key->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Llave «{$key->name}» revocada."]);

        return to_route('settings.api-keys');
    }
}
