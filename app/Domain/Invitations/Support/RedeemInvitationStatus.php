<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Support;

/**
 * Legal outcomes of redeeming an invitation. Mirrors the HTTP contract
 * the Next.js `/api/invitations/<token>/redeem` endpoint exposed, but
 * carries no HTTP knowledge itself — the Responder owns that mapping.
 */
enum RedeemInvitationStatus: string
{
    case Redeemed = 'redeemed';
    case Unauthenticated = 'unauthenticated';
    case NotRedeemable = 'not_redeemable';
    case Conflict = 'conflict';
}
