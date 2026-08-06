<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Results;

use App\Domain\Invitations\Support\RedeemInvitationStatus;

/**
 * Immutable outcome of a redeem attempt. Named constructors keep the
 * human message in sync with the status; callers MUST use them instead
 * of `new` so no branch can ship a status without its copy.
 */
final readonly class RedeemInvitationResult
{
    public function __construct(
        public RedeemInvitationStatus $status,
        public ?string $message = null,
    ) {}

    public static function redeemed(): self
    {
        return new self(RedeemInvitationStatus::Redeemed);
    }

    public static function unauthenticated(): self
    {
        return new self(RedeemInvitationStatus::Unauthenticated);
    }

    /** Revoked, already used, expired, or no such token. */
    public static function notRedeemable(): self
    {
        return new self(
            RedeemInvitationStatus::NotRedeemable,
            __('auth.ui.invitation_preview.expired_description'),
        );
    }

    /** The caller already belongs elsewhere or owns conflicting data. */
    public static function conflict(): self
    {
        return new self(
            RedeemInvitationStatus::Conflict,
            __('auth.ui.invitation_preview.already_member'),
        );
    }
}
