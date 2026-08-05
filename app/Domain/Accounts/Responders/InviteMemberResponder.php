<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Responders;

use App\Domain\Accounts\Actions\InviteMember;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use Illuminate\Http\RedirectResponse;

/**
 * Maps a {@see MemberActionResult} from {@see InviteMember}
 * to the HTTP response. Transport-only — never re-evaluates the
 * Action's rules. Branches on the {@see MemberActionStatus} enum:
 *
 *   - Success:    redirect back with a `invited => true` flash, so
 *                 the page can render a success toast.
 *   - Forbidden:  403 (covers both unauthenticated and Admin-less
 *                 attempts — the Action collapses them into a single
 *                 status).
 *
 * Invitations cannot trigger LastOwnerBlocked or NotMember in this
 * endpoint (the route model binding already 404s a missing account;
 * invitations do not affect Owner count), so those branches are
 * absent on purpose.
 */
final readonly class InviteMemberResponder
{
    public function __invoke(MemberActionResult $result): RedirectResponse
    {
        return match ($result->status) {
            MemberActionStatus::Success => $this->success(),
            MemberActionStatus::Forbidden => $this->forbidden(),
            MemberActionStatus::LastOwnerBlocked, MemberActionStatus::NotMember => $this->forbidden(),
        };
    }

    private function success(): RedirectResponse
    {
        return back()->with('invited', true);
    }

    private function forbidden(): RedirectResponse
    {
        abort(403);
    }
}
