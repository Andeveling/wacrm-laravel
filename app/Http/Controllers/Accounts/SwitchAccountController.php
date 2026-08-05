<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SwitchAccountController extends Controller
{
    /**
     * Switch the user's current account for the rest of the session.
     */
    public function __invoke(Request $request, Account $account): RedirectResponse
    {
        Gate::authorize('switchTo', $account);

        $request->session()->put('current_account_id', $account->id);

        return to_route('dashboard');
    }
}
