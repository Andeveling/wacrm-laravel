<?php

declare(strict_types=1);

use App\Domain\Accounts\Support\MemberActionStatus;
use App\Domain\Accounts\Support\MembershipRules;
use App\Models\Enums\AccountRole;

/**
 * The membership matrix, exhaustively.
 *
 * Each test enumerates every combination of the facts the rule reads
 * and states the expected status as a set of guards that spell out
 * their own precedence, instead of replaying the implementation's
 * early-return order. That precedence is the contract: authorization
 * decides before ADR 0002's Owner Protection, so an actor who may not
 * manage members never learns whether the Account is down to its last
 * Owner. Reordering the guards inside MembershipRules fails here.
 *
 * No database, no container, no HTTP — the point of ADR 0004's Domain
 * suite.
 */
it('decides every invitation combination', function (): void {
    $failures = [];

    foreach ([null, ...AccountRole::cases()] as $actor) {
        foreach (AccountRole::cases() as $invitedRole) {
            $canManage = $actor?->atLeast(AccountRole::Admin) ?? false;
            $handsOutOwnership = $invitedRole === AccountRole::Owner && $actor !== AccountRole::Owner;

            $expected = match (true) {
                ! $canManage => MemberActionStatus::Forbidden,
                $canManage && $handsOutOwnership => MemberActionStatus::Forbidden,
                default => MemberActionStatus::Success,
            };

            $actual = MembershipRules::forInvitation($actor, $invitedRole);

            if ($actual !== $expected) {
                $failures[] = sprintf(
                    'actor=%s invited=%s: expected %s, got %s',
                    $actor?->value ?? 'none',
                    $invitedRole->value,
                    $expected->value,
                    $actual->value,
                );
            }
        }
    }

    expect($failures)->toBe([]);
});

it('decides every role-change combination', function (): void {
    $failures = [];

    foreach ([null, ...AccountRole::cases()] as $actor) {
        foreach ([null, ...AccountRole::cases()] as $targetRole) {
            foreach (AccountRole::cases() as $newRole) {
                foreach ([0, 1, 2] as $ownerCount) {
                    $canManage = $actor?->atLeast(AccountRole::Admin) ?? false;
                    $isMember = $targetRole !== null;
                    $adminTouchesOwnerTier = $actor === AccountRole::Admin
                        && ($targetRole === AccountRole::Owner || $newRole === AccountRole::Owner);
                    $strandsAccount = $targetRole === AccountRole::Owner
                        && $newRole !== AccountRole::Owner
                        && $ownerCount <= 1;

                    $expected = match (true) {
                        ! $canManage => MemberActionStatus::Forbidden,
                        $canManage && ! $isMember => MemberActionStatus::NotMember,
                        $canManage && $isMember && $adminTouchesOwnerTier => MemberActionStatus::Forbidden,
                        $canManage && $isMember && ! $adminTouchesOwnerTier && $strandsAccount => MemberActionStatus::LastOwnerBlocked,
                        default => MemberActionStatus::Success,
                    };

                    $actual = MembershipRules::forRoleChange($actor, $targetRole, $newRole, $ownerCount);

                    if ($actual !== $expected) {
                        $failures[] = sprintf(
                            'actor=%s target=%s new=%s owners=%d: expected %s, got %s',
                            $actor?->value ?? 'none',
                            $targetRole?->value ?? 'none',
                            $newRole->value,
                            $ownerCount,
                            $expected->value,
                            $actual->value,
                        );
                    }
                }
            }
        }
    }

    expect($failures)->toBe([]);
});

it('decides every removal combination', function (): void {
    $failures = [];

    foreach ([null, ...AccountRole::cases()] as $actor) {
        foreach ([null, ...AccountRole::cases()] as $targetRole) {
            foreach ([false, true] as $targetIsActor) {
                foreach ([0, 1, 2] as $ownerCount) {
                    $canManage = $actor?->atLeast(AccountRole::Admin) ?? false;
                    $isMember = $targetRole !== null;
                    $strandsAccount = $targetRole === AccountRole::Owner && $ownerCount <= 1;

                    $expected = match (true) {
                        ! $canManage => MemberActionStatus::Forbidden,
                        $canManage && $targetIsActor => MemberActionStatus::Forbidden,
                        $canManage && ! $targetIsActor && ! $isMember => MemberActionStatus::NotMember,
                        $canManage && ! $targetIsActor && $isMember && $strandsAccount => MemberActionStatus::LastOwnerBlocked,
                        default => MemberActionStatus::Success,
                    };

                    $actual = MembershipRules::forRemoval($actor, $targetRole, $targetIsActor, $ownerCount);

                    if ($actual !== $expected) {
                        $failures[] = sprintf(
                            'actor=%s target=%s self=%s owners=%d: expected %s, got %s',
                            $actor?->value ?? 'none',
                            $targetRole?->value ?? 'none',
                            $targetIsActor ? 'yes' : 'no',
                            $ownerCount,
                            $expected->value,
                            $actual->value,
                        );
                    }
                }
            }
        }
    }

    expect($failures)->toBe([]);
});

/**
 * An Admin may remove an Owner while another Owner remains, but may
 * not demote one. The asymmetry is easy to read as a bug in the matrix
 * above, so it is pinned on its own: a removal leaves the role
 * hierarchy intact, a demotion rewrites the tier the Admin sits inside.
 */
it('lets an Admin remove a non-sole Owner but never demote one', function (): void {
    expect(MembershipRules::forRemoval(AccountRole::Admin, AccountRole::Owner, false, 2))
        ->toBe(MemberActionStatus::Success)
        ->and(MembershipRules::forRoleChange(AccountRole::Admin, AccountRole::Owner, AccountRole::Member, 2))
        ->toBe(MemberActionStatus::Forbidden);
});
