<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Account;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects an email that already belongs to the given Account via
 * the account_user pivot. The check is per-account: the same email
 * may still be invited from a different account (cross-tenant
 * membership is legal — see InviteMemberTest).
 *
 * The Account's `users()` relation is reused so the join matches the
 * one ListMembers uses to render the roster; the pivot already
 * filters to this account, so the whereHas subquery is cheap.
 */
final readonly class NotMemberOfAccount implements ValidationRule
{
    public function __construct(private Account $account) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->account->users()->where('users.email', $value)->exists()) {
            $fail('The :attribute is already a member of this account.');
        }
    }
}
