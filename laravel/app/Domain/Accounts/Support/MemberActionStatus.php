<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use App\Domain\Accounts\Results\MemberActionResult;

/**
 * Outcome of an Action that mutates Account membership. The Action
 * returns a {@see MemberActionResult}
 * carrying one of these cases; the Responder maps the case to HTTP /
 * Inertia without re-evaluating the rules. Backed by the lowercased
 * case name so it serializes predictably to JSON and storage.
 *
 * The four cases cover every legal branch of the member-management
 * Actions (change role, remove member, accept invitation, etc.) per
 * ADR 0002:
 *  - Success: the mutation went through.
 *  - LastOwnerBlocked: the mutation was rejected because the target
 *    is the last remaining Owner of the Account.
 *  - NotMember: the actor is not a member of the Account at all.
 *  - Forbidden: the actor is a member but their role lacks the
 *    capability (AccountPolicy rejected).
 */
enum MemberActionStatus: string
{
    case Success = 'success';
    case LastOwnerBlocked = 'last_owner_blocked';
    case NotMember = 'not_member';
    case Forbidden = 'forbidden';

    /**
     * Translation key consumed by the Responder (and any future view).
     * Resolves under the `accounts.members.status.*` namespace of the
     * `lang/{locale}/accounts.php` file, keeping i18n keys out of the
     * Domain layer.
     */
    public function label(): string
    {
        return match ($this) {
            self::Success => 'accounts.members.status.success',
            self::LastOwnerBlocked => 'accounts.members.status.last_owner_blocked',
            self::NotMember => 'accounts.members.status.not_member',
            self::Forbidden => 'accounts.members.status.forbidden',
        };
    }
}
