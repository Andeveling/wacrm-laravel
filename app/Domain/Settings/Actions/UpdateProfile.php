<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Update the user's profile information. Changing the email resets
 * verification so the new address has to be confirmed again.
 *
 * One outcome (the FormRequest rejects everything else) — ADR 0001
 * rule 4, no Result object and no Responder.
 */
final class UpdateProfile
{
    public function __invoke(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
