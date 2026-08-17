<?php

namespace App\Http\Middleware;

use App\Models\Scopes\AccountScope;
use App\Support\CurrentAccount;
use App\Support\CurrentAccountResolver;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAccount
{
    public function __construct(private CurrentAccountResolver $resolver) {}

    /**
     * Resolve the Current Account, bind it for the request, and scope
     * tenant-aware models to it. Session, last_account_id, the unique
     * Team, Personal, then the first remaining membership. No switcher.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $this->resolver->membership($request);

        if ($membership === null) {
            return Inertia::render('accounts/no-account')->toResponse($request);
        }

        $role = $membership->pivot?->role;
        abort_if($role === null, 403);

        $this->resolver->remember($request, $membership);

        app()->instance(
            CurrentAccount::class,
            new CurrentAccount($membership->id, $role),
        );
        app()->instance(AccountScope::CONTAINER_KEY, $membership->id);

        return $next($request);
    }
}
