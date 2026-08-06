<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Http\Requests\Settings\ProfileDeleteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Delete the user's account. Logout happens before the delete so the
 * session no longer references a row that is about to disappear; the
 * session is then invalidated and its CSRF token rotated.
 */
final class DestroyProfile
{
    public function __invoke(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
