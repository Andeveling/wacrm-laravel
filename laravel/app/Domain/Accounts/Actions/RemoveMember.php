<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\RemoveMemberResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes a membership (account_user pivot row) from a Team Account.
 *
 * The Action is the invokable controller for
 * DELETE /accounts/{account}/members/{member} and delegates the
 * response shape to {@see RemoveMemberResponder}. Every legal branch
 * of the membership-mutation flow is encoded in the
 * {@see MemberActionResult} status enum so the Responder stays
 * transport-only (ADR 0001).
 *
 * Two invariants gate the DELETE:
 *
 *  1. Self-removal is forbidden — a User cannot remove themselves from
 *     a Team Account via this endpoint (ADR 0002 §3 covers the future
 *     "leave Account" flow). Even the Account's sole Owner is blocked.
 *  2. Owner Protection (ADR 0002): when the target holds the Owner
 *     role and they are the last remaining Owner of the Account, the
 *     DELETE is refused. Promote another Owner first.
 *
 * The role check uses AccountPolicy::manageMembers so the Admin+
 * capability is the single source of truth, mirroring InviteMember
 * and ChangeMemberRole.
 */
final readonly class RemoveMember
{
    public function __construct(private RemoveMemberResponder $responder) {}

    public function __invoke(Request $request, Account $account, User $member): Response
    {
        $actor = $request->user();

        if ($actor === null) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        // Self-removal block: a User cannot yank themselves out of a
        // Team Account via this endpoint.
        if ($actor->is($member)) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        if (! $actor->can('manageMembers', $account)) {
            return ($this->responder)(MemberActionResult::forbidden());
        }

        $pivot = $this->findPivot($account, $member);

        if ($pivot === null) {
            return ($this->responder)(MemberActionResult::notMember());
        }

        // Owner Protection: refuse the mutation if the target is the
        // last Owner, BEFORE any DB write.
        if ($this->isLastOwner($account, $pivot)) {
            return ($this->responder)(MemberActionResult::lastOwnerBlocked());
        }

        $account->users()->detach($member->id);

        return ($this->responder)(MemberActionResult::success($pivot));
    }

    private function findPivot(Account $account, User $member): ?AccountUser
    {
        $pivot = $account->users()
            ->whereKey($member->id)
            ->first();

        return $pivot?->pivot;
    }

    /**
     * True when $pivot holds the Owner role and is the only Owner of
     * $account. Counting against the live DB (not the in-memory pivot)
     * is intentional: a concurrent demotion could otherwise sneak past
     * the check.
     */
    private function isLastOwner(Account $account, AccountUser $pivot): bool
    {
        if ($pivot->role !== AccountRole::Owner) {
            return false;
        }

        return $account->users()
            ->wherePivot('role', AccountRole::Owner->value)
            ->count() === 1;
    }
}
