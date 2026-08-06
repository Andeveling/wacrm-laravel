<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Responders;

use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single Responder for every Account-membership flow — invite,
 * change role, remove (ADR 0005).
 *
 * All three produce the same {@see MemberActionStatus}, so all three
 * used to carry their own copy of the same four-case match. The copies
 * had drifted: one answered 403 where the others answered 404 for a
 * status it could not even produce.
 *
 * What genuinely differs per flow is presentation, and it arrives as
 * arguments:
 *
 *  - $flash — the session key the page reads to know what happened.
 *  - $toast — Inertia toast copy, or null for a flow that shows none.
 *  - $route — the named route to land on; null redirects back, which is
 *    what a flow triggered from a modal wants.
 *
 * The status mapping itself is fixed and shared:
 *  - Success → 302 to $route (or back) carrying $flash.
 *  - LastOwnerBlocked → 302 to the same destination with a validation
 *    error keyed `last_owner_blocked`, matching the ADR 0002 contract.
 *  - Forbidden → 403.
 *  - NotMember → 404.
 *
 * Transport only: it never re-evaluates the rules that produced the
 * status. Copy is hardcoded in Spanish per project decision (no i18n
 * layer); revisit if issue #24 lands.
 */
final readonly class MemberActionResponder
{
    public function __invoke(
        MemberActionResult $result,
        string $flash,
        ?string $toast = null,
        ?string $route = null,
    ): Response {
        return match ($result->status) {
            MemberActionStatus::Success => $this->success($result, $flash, $toast, $route),
            MemberActionStatus::LastOwnerBlocked => $this->lastOwnerBlocked($result, $route),
            MemberActionStatus::Forbidden => $this->forbidden(),
            MemberActionStatus::NotMember => $this->notMember(),
        };
    }

    private function success(
        MemberActionResult $result,
        string $flash,
        ?string $toast,
        ?string $route,
    ): RedirectResponse {
        if ($toast !== null) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $toast,
            ]);
        }

        return $this->redirect($result, $route)->with($flash, true);
    }

    private function lastOwnerBlocked(MemberActionResult $result, ?string $route): RedirectResponse
    {
        return $this->redirect($result, $route)->withErrors([
            'last_owner_blocked' => 'Promueve a otro Owner antes de degradar el rol.',
        ]);
    }

    /**
     * A named route needs the Account to fill its {account} placeholder;
     * the Result carries it for exactly this reason. Without a route we
     * bounce back to wherever the request came from.
     */
    private function redirect(MemberActionResult $result, ?string $route): RedirectResponse
    {
        if ($route === null) {
            return back();
        }

        return redirect()->route($route, $result->account);
    }

    private function forbidden(): Response
    {
        abort(403);
    }

    private function notMember(): Response
    {
        abort(404);
    }
}
