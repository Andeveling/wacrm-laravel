<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Results;

use App\Domain\Accounts\Support\MemberActionStatus;
use App\Models\Account;

/**
 * Immutable value object returned by every Account-membership Action.
 *
 * It carries the two things MemberActionResponder needs and nothing
 * else: the outcome, and the Account whose members page a redirect may
 * target. Copy lives in the Responder (ADR 0005), so the
 * Result never holds a message; the Actions never re-render the
 * affected pivot, so it never holds one either.
 *
 * Pure data — no Eloquent queries, no HTTP awareness — so the Actions
 * stay testable without booting the Laravel container.
 */
final readonly class MemberActionResult
{
    public function __construct(
        public MemberActionStatus $status,
        public ?Account $account = null,
    ) {}
}
