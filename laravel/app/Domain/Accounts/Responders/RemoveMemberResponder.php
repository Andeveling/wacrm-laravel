<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Responders;

use App\Domain\Accounts\Actions\RemoveMember;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maps the four {@see MemberActionResult} cases produced by
 * {@see RemoveMember} to HTTP responses:
 *  - Success → 302 back with a `member_removed` flash so the UI can toast.
 *  - LastOwnerBlocked → 302 back with a validation error keyed by the
 *    status label, matching the ADR 0002 contract.
 *  - Forbidden → 403.
 *  - NotMember → 404.
 *
 * Responder is pure transport — it never re-evaluates the rules that
 * produced the status. Translation key for the success flash lives in
 * lang/ (issue #24).
 */
final readonly class RemoveMemberResponder
{
    public function __invoke(MemberActionResult $result): Response
    {
        return match ($result->status) {
            MemberActionStatus::Success => $this->success(),
            MemberActionStatus::LastOwnerBlocked => $this->lastOwnerBlocked($result),
            MemberActionStatus::Forbidden => $this->forbidden(),
            MemberActionStatus::NotMember => $this->notMember(),
        };
    }

    private function success(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('accounts.members.status.success'),
        ]);

        return back()->with('member_removed', true);
    }

    private function lastOwnerBlocked(MemberActionResult $result): RedirectResponse
    {
        return back()->withErrors([
            $result->status->label() => $result->message ?? __('accounts.members.status.last_owner_blocked'),
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
