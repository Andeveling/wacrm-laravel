<?php

namespace App\Support;

use App\Models\Enums\AccountRole;

final readonly class CurrentAccount
{
    public function __construct(
        private string $id,
        private AccountRole $role,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function role(): AccountRole
    {
        return $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === AccountRole::Owner;
    }

    public function isAdmin(): bool
    {
        return $this->role->atLeast(AccountRole::Admin);
    }

    public function isMember(): bool
    {
        return $this->role->atLeast(AccountRole::Member);
    }
}
