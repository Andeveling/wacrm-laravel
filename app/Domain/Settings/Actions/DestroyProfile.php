<?php

declare(strict_types=1);

namespace App\Domain\Settings\Actions;

use App\Domain\Meta\Services\DisableWhatsappRoutingForAccount;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Delete the user's account. Logout happens before the delete so the
 * session no longer references a row that is about to disappear; the
 * session is then invalidated and its CSRF token rotated.
 *
 * Before the user row is removed, every Account left without remaining
 * members has WhatsApp routing disabled and encrypted tokens stripped
 * so deleted tenants cannot keep processing traffic.
 */
final class DestroyProfile
{
    public function __construct(private DisableWhatsappRoutingForAccount $disableWhatsapp) {}

    public function __invoke(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        DB::transaction(function () use ($user): void {
            $accountIds = $user->accounts()->pluck('accounts.id');

            foreach ($accountIds as $accountId) {
                $remainingMembers = DB::table('account_user')
                    ->where('account_id', $accountId)
                    ->where('user_id', '!=', $user->id)
                    ->count();

                if ($remainingMembers === 0) {
                    $this->disableWhatsapp->handle((string) $accountId);
                }
            }

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
