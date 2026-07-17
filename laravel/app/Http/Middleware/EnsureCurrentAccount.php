<?php

namespace App\Http\Middleware;

use App\Models\Scopes\AccountScope;
use App\Support\CurrentAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccount
{
    /**
     * Resolve current_account_id from session, bind CurrentAccount for the
     * request, and scope tenant-aware models to it. Soft-redirects to the
     * account switcher when no account is selected yet; hard-403s when the
     * session points at an account the user no longer belongs to.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accountId = $request->session()->get('current_account_id');

        if ($accountId === null) {
            return redirect()->route('accounts.switch');
        }

        $membership = $request->user()
            ->accounts()
            ->whereKey($accountId)
            ->first();

        abort_if($membership === null, 403);

        app()->instance(
            CurrentAccount::class,
            new CurrentAccount($membership->id, $membership->pivot->role),
        );
        app()->instance(AccountScope::CONTAINER_KEY, $membership->id);

        return $next($request);
    }
}
