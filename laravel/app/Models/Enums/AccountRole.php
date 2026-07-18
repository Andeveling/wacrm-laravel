<?php

namespace App\Models\Enums;

/**
 * Role a user holds in an account, via the account_user pivot. The
 * hierarchy is a flat ordinal (owner=4 … viewer=1), mirroring the
 * legacy account_role_enum so role comparisons stay one number check.
 * What each role may do lives in AccountPolicy — the single source of
 * truth for capabilities; compare roles with atLeast(), never inline.
 */
enum AccountRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Numeric rank of the role. Higher = more privileged.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Member => 2,
            self::Viewer => 1,
        };
    }

    /**
     * Whether this role is at least as privileged as the given one.
     */
    public function atLeast(self $minimum): bool
    {
        return $this->rank() >= $minimum->rank();
    }
}
