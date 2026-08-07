<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Services;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Single owner of invitation row construction. Centralises the
 * plaintext-token → SHA-256 hash step and the default expiry so no
 * caller (existing StoreInvitationController, InviteMember Action,
 * future endpoints) can accidentally persist a plaintext token or
 * forget the expiration window.
 *
 * Per ADR 0001 rule 4, this is not a Domain Action — there is no
 * decision tree to extract. It is a thin, side-effectful helper
 * invoked by both the legacy store route and the new account-scoped
 * route.
 */
class InvitationIssuer
{
    /**
     * Default invitation lifetime in days, mirroring the legacy
     * StoreInvitationController behaviour so callers that do not
     * specify an expiry stay backwards-compatible.
     */
    public const DEFAULT_EXPIRY_DAYS = 7;

    /**
     * Issue a new invitation for the account identified by $accountId,
     * attributed to $inviter. The plaintext token is returned alongside
     * the persisted Invitation because it exists only in memory — the
     * row stores just the SHA-256 hash, so this is the caller's only
     * chance to build a redeemable link.
     *
     * @param  string  $accountId  UUID of the target Account.
     * @param  string  $role  one of admin|member|viewer (validated upstream).
     * @param  string|null  $label  optional human-readable label.
     * @param  int|null  $expiresInDays  overrides the default expiry window.
     * @return array{invitation: Invitation, token: string}
     */
    public function issue(
        string $accountId,
        User $inviter,
        string $role,
        ?string $label = null,
        ?int $expiresInDays = null,
    ): array {
        $token = Str::random(43);

        $invitation = Invitation::create([
            'account_id' => $accountId,
            'token_hash' => hash('sha256', $token),
            'role' => $role,
            'invited_by' => $inviter->id,
            'label' => $label,
            'expires_at' => now()->addDays($expiresInDays ?? self::DEFAULT_EXPIRY_DAYS),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }
}
