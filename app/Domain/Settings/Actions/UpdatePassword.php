<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Update the user's password. The current-password check and the
 * strength rules live in the FormRequest, so the only outcome here is
 * success — ADR 0001 rule 4, no Result object and no Responder.
 */
final class UpdatePassword
{
    public function __invoke(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
