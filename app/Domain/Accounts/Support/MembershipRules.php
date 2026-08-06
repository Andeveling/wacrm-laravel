<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use App\Models\Enums\AccountRole;

/**
 * Every membership decision of the Accounts context, as pure PHP.
 *
 * The three membership Actions (invite, change role, remove) used to
 * each carry a private copy of these rules, and the copies had already
 * drifted apart: two consulted AccountPolicy for the Admin floor while
 * the third re-derived it by hand, and only one ordered authorization
 * before ADR 0002's Owner Protection check.
 *
 * This module takes facts — the actor's role, the target's current
 * role, the role being applied, how many Owners the Account has — and
 * returns a {@see MemberActionStatus}. It reads no database, knows no
 * HTTP and is never resolved from the container, so the whole matrix
 * is verifiable in the Domain suite (ADR 0004).
 *
 * Two orderings are deliberate and shared by all three flows:
 *
 *  1. Authorization first. An actor who may not manage members learns
 *     nothing about the Account — not whether the target is a member,
 *     not how many Owners remain. Only then do NotMember and
 *     LastOwnerBlocked become reachable.
 *  2. Owner Protection last, and always evaluated against the live
 *     Owner count the caller passes in. The caller is responsible for
 *     reading that count inside the same transaction as the write
 *     (ADR 0002) — the rule itself cannot enforce atomicity.
 *
 * Removal and role change treat Owner rows differently, on purpose. An
 * Admin may remove an Owner (as long as another Owner remains) but may
 * not demote one, because a demotion rewrites the role hierarchy the
 * Admin sits inside, while a removal leaves it intact.
 */
final class MembershipRules
{
    /**
     * The Admin floor for member management. AccountPolicy::manageMembers
     * delegates here so the gate the UI reads and the rule the Actions
     * run are the same decision.
     */
    public static function canManageMembers(?AccountRole $actor): bool
    {
        return $actor?->atLeast(AccountRole::Admin) ?? false;
    }

    /**
     * Whether $actor may issue an Invitation carrying $invitedRole.
     *
     * Owner Protection does not apply: an Invitation is a pending offer
     * that cannot change the Owner count, so LastOwnerBlocked is
     * unreachable here.
     */
    public static function forInvitation(?AccountRole $actor, AccountRole $invitedRole): MemberActionStatus
    {
        if (! self::canManageMembers($actor)) {
            return MemberActionStatus::Forbidden;
        }

        if ($invitedRole === AccountRole::Owner && $actor !== AccountRole::Owner) {
            return MemberActionStatus::Forbidden;
        }

        return MemberActionStatus::Success;
    }

    /**
     * Whether $actor may move a member currently holding $targetRole to
     * $newRole. A null $targetRole means the user is not a member.
     */
    public static function forRoleChange(
        ?AccountRole $actor,
        ?AccountRole $targetRole,
        AccountRole $newRole,
        int $ownerCount,
    ): MemberActionStatus {
        if (! self::canManageMembers($actor)) {
            return MemberActionStatus::Forbidden;
        }

        if ($targetRole === null) {
            return MemberActionStatus::NotMember;
        }

        // An Admin manages members but never the Owner tier: touching an
        // Owner row would weaken accountability, and promoting to Owner
        // would let the Admin escalate themselves.
        if ($actor === AccountRole::Admin
            && ($targetRole === AccountRole::Owner || $newRole === AccountRole::Owner)) {
            return MemberActionStatus::Forbidden;
        }

        // ADR 0002 — the last Owner cannot leave the Owner tier.
        if ($targetRole === AccountRole::Owner
            && $newRole !== AccountRole::Owner
            && $ownerCount <= 1) {
            return MemberActionStatus::LastOwnerBlocked;
        }

        return MemberActionStatus::Success;
    }

    /**
     * Whether $actor may delete the membership of a user currently
     * holding $targetRole. A null $targetRole means the user is not a
     * member.
     *
     * $targetIsActor blocks self-removal: leaving an Account is a
     * separate flow (ADR 0002 §3), and this endpoint refuses it even
     * for an Owner who is not the last one.
     */
    public static function forRemoval(
        ?AccountRole $actor,
        ?AccountRole $targetRole,
        bool $targetIsActor,
        int $ownerCount,
    ): MemberActionStatus {
        if (! self::canManageMembers($actor)) {
            return MemberActionStatus::Forbidden;
        }

        if ($targetIsActor) {
            return MemberActionStatus::Forbidden;
        }

        if ($targetRole === null) {
            return MemberActionStatus::NotMember;
        }

        // ADR 0002 — removing the last Owner would strand the Account.
        if ($targetRole === AccountRole::Owner && $ownerCount <= 1) {
            return MemberActionStatus::LastOwnerBlocked;
        }

        return MemberActionStatus::Success;
    }
}
