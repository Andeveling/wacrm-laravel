<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Results;

use App\Domain\Accounts\Support\MemberActionStatus;
use App\Models\Account;
use App\Models\AccountUser;

/**
 * Immutable value object returned by every Account-membership Action.
 * Carries the {@see MemberActionStatus} outcome plus the entities the
 * Responder needs to render the response (the affected member, the
 * Account, and an optional human message). Pure data — no Eloquent
 * queries, no HTTP awareness — so Actions stay unit-testable without
 * booting the Laravel container.
 *
 * Named constructors express every legal outcome; callers MUST go
 * through them instead of `new` so the message defaults stay in sync
 * with the corresponding status.
 */
final readonly class MemberActionResult
{
    public function __construct(
        public MemberActionStatus $status,
        public ?AccountUser $member = null,
        public ?Account $account = null,
        public ?string $message = null,
    ) {}

    /**
     * Mutation succeeded. The affected membership is returned so the
     * Responder can re-render the row without re-querying.
     */
    public static function success(AccountUser $member): self
    {
        return new self(MemberActionStatus::Success, member: $member);
    }

    /**
     * Mutation succeeded but produced an Invitation rather than an
     * AccountUser pivot (i.e. the action invites — the membership
     * only materialises when the invitee accepts the token). The
     * Responder can read $message to flash a human copy; no
     * AccountUser exists yet so $member stays null.
     */
    public static function successForInvitation(?string $message = null): self
    {
        return new self(
            MemberActionStatus::Success,
            message: $message ?? 'Invitation sent.',
        );
    }

    /**
     * The mutation would have left the Account without an Owner, so
     * the Action refused to run (ADR 0002 — Owner Protection
     * Invariant). The default message matches the seed copy pending
     * the i18n pass tracked in issue #24.
     */
    public static function lastOwnerBlocked(?string $message = null): self
    {
        return new self(
            MemberActionStatus::LastOwnerBlocked,
            message: $message ?? 'Promote another Owner before demoting your role.',
        );
    }

    /**
     * The actor is not a member of the target Account. No mutation
     * was attempted.
     */
    public static function notMember(): self
    {
        return new self(MemberActionStatus::NotMember);
    }

    /**
     * The actor is a member but their role lacks the capability
     * required by the mutation (AccountPolicy rejected). No mutation
     * was attempted.
     */
    public static function forbidden(): self
    {
        return new self(MemberActionStatus::Forbidden);
    }
}
