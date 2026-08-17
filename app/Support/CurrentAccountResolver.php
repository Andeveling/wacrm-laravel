<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Enums\AccountType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CurrentAccountResolver
{
    /**
     * Session membership, last_account_id, the unique Team, Personal,
     * then the first remaining membership. Null when the User has none.
     */
    public function membership(Request $request): ?Account
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $memberships = $user->accounts()->orderBy('name')->get();
        $sessionId = $request->session()->get('current_account_id');

        if ($memberships->isEmpty()) {
            abort_if($sessionId !== null, 403);

            return null;
        }

        $fromSession = $memberships->firstWhere('id', $sessionId);

        if ($fromSession !== null) {
            return $fromSession;
        }

        $fromLast = $memberships->firstWhere('id', $user->last_account_id);

        if ($fromLast !== null) {
            return $fromLast;
        }

        return $this->fallback($memberships);
    }

    public function remember(Request $request, Account $membership): void
    {
        $request->session()->put('current_account_id', $membership->id);

        $user = $request->user();

        if ($user instanceof User && $user->last_account_id !== $membership->id) {
            $user->forceFill(['last_account_id' => $membership->id])->save();
        }
    }

    /**
     * @param  Collection<int, Account>  $memberships
     */
    private function fallback(Collection $memberships): Account
    {
        $teams = $memberships->where('type', AccountType::Team);

        if ($teams->count() === 1) {
            return $teams->first();
        }

        return $memberships->firstWhere('type', AccountType::Personal)
            ?? $memberships->first();
    }
}
