<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\RemoveMemberResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Domain\Accounts\Support\MembershipRules;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes a membership (account_user pivot row) from a Team Account.
 *
 * The Action is the invokable controller for
 * DELETE /accounts/{account}/members/{member}. It reads the facts,
 * asks {@see MembershipRules} for the verdict, and hands the
 * {@see MemberActionResult} to {@see RemoveMemberResponder}, which
 * stays transport-only (ADR 0001).
 *
 * Self-removal and Owner Protection (ADR 0002) both live in the rules
 * module, shared with ChangeMemberRole. Note the deliberate asymmetry
 * between the two: an Admin may remove an Owner while another Owner
 * remains, but may not demote one.
 *
 * Facts and DELETE share one transaction that opens by locking the
 * Account row, so a concurrent mutation cannot invalidate the Owner
 * count between the check and the write.
 */
final readonly class RemoveMember
{
    public function __construct(private RemoveMemberResponder $responder) {}

    public function __invoke(Request $request, Account $account, User $member): Response
    {
        $actor = $request->user();

        $result = DB::transaction(function () use ($account, $actor, $member): MemberActionResult {
            Account::query()->whereKey($account->getKey())->lockForUpdate()->first();

            $pivot = $this->pivotOf($account, $member);

            $status = MembershipRules::forRemoval(
                $actor?->roleIn($account),
                $pivot?->role,
                $actor?->is($member) ?? false,
                $this->ownerCount($account),
            );

            if ($status !== MemberActionStatus::Success) {
                return new MemberActionResult($status, account: $account);
            }

            $account->users()->detach($member->id);

            return new MemberActionResult(
                MemberActionStatus::Success,
                member: $pivot,
                account: $account,
            );
        });

        return ($this->responder)($result);
    }

    /**
     * The pivot row joining $member to $account, or null when they are
     * not a member.
     */
    private function pivotOf(Account $account, User $member): ?AccountUser
    {
        return $account->users()->whereKey($member->id)->first()?->pivot;
    }

    private function ownerCount(Account $account): int
    {
        return $account->users()
            ->wherePivot('role', AccountRole::Owner->value)
            ->count();
    }
}
