<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Models\Account;
use App\Support\ProductSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class SwitchAccount
{
    /**
     * Switch the user's current account for the rest of the session.
     */
    public function __invoke(Request $request, Account $account, ProductSection $section): RedirectResponse
    {
        Gate::authorize('switchTo', $account);

        $request->user()->forceFill(['last_account_id' => $account->id])->save();
        $request->session()->put('current_account_id', $account->id);

        return to_route($section->routeName($request->headers->get('referer')));
    }
}
