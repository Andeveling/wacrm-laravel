<?php

use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

/*
 * Public REST API surface (`/api/v1/*`).
 *
 * Every route in this file lives behind the `api_key` bearer-token guard.
 * The full endpoint catalog (messages, contacts, conversations, broadcasts,
 * ...) lives in downstream tickets — this file ships the bearer-token
 * mounting seam so later tickets slot in without re-plumbing auth.
 */

Route::middleware([AuthenticateApiKey::class])->prefix('v1')->group(function () {
    Route::get('me', function () {
        /** @var \App\Models\ApiKey $apiKey */
        $apiKey = auth('api_key')->user();

        return response()->json([
            'data' => [
                'account' => [
                    'id' => $apiKey->account_id,
                    'name' => $apiKey->account?->name,
                ],
                'key' => [
                    'id' => $apiKey->id,
                    'scopes' => $apiKey->scopes ?? [],
                ],
            ],
        ]);
    })->name('api.v1.me');
});
