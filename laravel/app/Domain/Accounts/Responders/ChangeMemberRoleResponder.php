<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Responders;

use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maps the four {@see MemberActionResult} cases produced by the
 * ChangeMemberRole / RemoveMember Actions to HTTP responses:
 *  - Success → 302 to the members page with a `role_changed` flash
 *    so the UI can toast.
 *  - LastOwnerBlocked → 302 to the members page with a validation
 *    error keyed `last_owner_blocked`, matching the ADR 0002 contract.
 *  - Forbidden → 403.
 *  - NotMember → 404.
 *
 * Responder is pure transport — it never re-evaluates the rules that
 * produced the status. Translation key for the success flash lives
 * in lang/ (issue #24).
 */
final readonly class ChangeMemberRoleResponder
{
    public function __invoke(MemberActionResult $result): RedirectResponse|Response
    {
        return match ($result->status) {
            MemberActionStatus::Success => $this->success($result),
            MemberActionStatus::LastOwnerBlocked => $this->lastOwnerBlocked($result),
            MemberActionStatus::Forbidden => $this->forbidden(),
            MemberActionStatus::NotMember => $this->notMember(),
        };
    }

    private function success(MemberActionResult $result): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('accounts.members.status.success'),
        ]);

        return redirect()
            ->route('accounts.members.index', $result->account)
            ->with('role_changed', true);
    }

    private function lastOwnerBlocked(MemberActionResult $result): RedirectResponse
    {
        return redirect()
            ->route('accounts.members.index', $result->account)
            ->withErrors([
                'last_owner_blocked' => $result->message ?? __('accounts.members.status.last_owner_blocked'),
            ]);
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
