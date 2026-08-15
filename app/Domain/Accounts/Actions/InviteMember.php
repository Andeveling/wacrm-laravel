<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\MemberActionResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Domain\Accounts\Support\MembershipRules;
use App\Domain\Invitations\Services\InvitationIssuer;
use App\Http\Requests\Accounts\InviteMemberRequest;
use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Invitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
 * ownerless. LastOwnerBlocked is therefore intentionally absent — the
 * rules module cannot return it for this flow.
 *
 * The verdict comes from {@see MembershipRules}, shared with
 * ChangeMemberRole and RemoveMember, so the Admin floor is decided in
 * one place. Token hashing + expiry live in {@see InvitationIssuer} so
 * the legacy /invitations store route and this Action share one source
 * of truth (no duplication per ADR 0001 rule 1).
 */
final readonly class InviteMember
{
    public function __construct(
        private InvitationIssuer $issuer,
        private MemberActionResponder $responder,
    ) {}

    public function __invoke(InviteMemberRequest $request, Account $account): Response
    {
        $actor = $request->user();
        $validated = $request->validated();

        $status = MembershipRules::forInvitation(
            $actor?->roleIn($account),
            AccountRole::from((string) $validated['role']),
        );

        $invitationUrl = null;

        if ($actor !== null && $status === MemberActionStatus::Success) {
            $issued = DB::transaction(function () use ($account, $actor, $validated): array {
                Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

                if (Invitation::withoutGlobalScopes()
                    ->where('account_id', $account->id)
                    ->where('email', $validated['email'])
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'email' => 'This email already has an active invitation for this account.',
                    ]);
                }

                return $this->issuer->issue(
                    accountId: $account->id,
                    inviter: $actor,
                    role: $validated['role'],
                    email: $validated['email'],
                );
            });

            $invitationUrl = route('invitations.preview', $issued['token']);
        }

        return ($this->responder)(
            new MemberActionResult($status, account: $account),
            flash: 'invited',
            redirectData: $invitationUrl === null ? [] : ['invitation_url' => $invitationUrl],
        );
    }
}
