<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\InviteMemberResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Invitations\Services\InvitationIssuer;
use App\Http\Requests\Accounts\InviteMemberRequest;
use App\Models\Account;
use App\Models\Enums\AccountRole;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invites a new member to the given Account by issuing an invitation
 * row. This is the invokable controller for
 * POST /accounts/{account}/members (issue #44).
 *
 * The Action does not decide HTTP shape — it builds a
 * {@see MemberActionResult} and hands it to the Responder, which is
 * the only place that knows about redirects and status codes.
 *
 * Two branches cover the legal outcomes:
 *
 *   - Success:    the Invitation row was issued.
 *   - Forbidden:  actor cannot manage members, or an Admin attempts
 *                 to issue an Owner invitation.
 *
 * Owner-protection (ADR 0002) does not apply to invitations: an
 * Invitation does not change Owner count, only creates a pending
 * offer. The not-yet-accepted Invitation cannot leave the Account
 * ownerless. LastOwnerBlocked is therefore intentionally absent.
 *
 * Token hashing + expiry live in {@see InvitationIssuer} so the
 * legacy /invitations store route and this Action share one source
 * of truth (no duplication per ADR 0001 rule 1).
 */
final readonly class InviteMember
{
    public function __construct(
        private InvitationIssuer $issuer,
        private InviteMemberResponder $responder,
    ) {}

    public function __invoke(InviteMemberRequest $request, Account $account): Response
    {
        $actor = $request->user();

        if ($actor === null) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        if (! $actor->can('manageMembers', $account)) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        $validated = $request->validated();
        if ($validated['role'] === AccountRole::Owner->value && $actor->roleIn($account) !== AccountRole::Owner) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        $this->issuer->issue(
            accountId: $account->id,
            inviter: $actor,
            role: $validated['role'],
        );

        return ($this->responder)(MemberActionResult::successForInvitation());
    }
}
