<?php

namespace App\Support;

final readonly class CurrentAccount
{
    public function __construct(
        private string $id,
        private string $role,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }
}
