<?php

declare(strict_types=1);

use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Models\Account;

covers(MemberActionResult::class);

it('carries the status and the Account a redirect may target', function (): void {
    $account = new Account(['name' => 'Acme Co']);

    $result = new MemberActionResult(MemberActionStatus::LastOwnerBlocked, $account);

    expect($result->status)->toBe(MemberActionStatus::LastOwnerBlocked)
        ->and($result->account)->toBe($account);
});

it('defaults to no Account for outcomes that abort instead of redirecting', function (): void {
    $result = new MemberActionResult(MemberActionStatus::Forbidden);

    expect($result->account)->toBeNull();
});
