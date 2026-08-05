<?php

declare(strict_types=1);

use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Models\AccountUser;

it('returns a translation key for every MemberActionStatus case', function (): void {
    foreach (MemberActionStatus::cases() as $case) {
        expect($case->label())
            ->toBeString()
            ->not->toBe('');
    }
});

it('returns distinct labels for each MemberActionStatus case', function (): void {
    $labels = array_map(
        static fn (MemberActionStatus $case): string => $case->label(),
        MemberActionStatus::cases(),
    );

    expect($labels)->toHaveCount(count(array_unique($labels)));
});

it('builds a success result carrying the affected member', function (): void {
    $member = new AccountUser([
        'account_id' => '01900000-0000-7000-8000-000000000001',
        'user_id' => 42,
    ]);

    $result = MemberActionResult::success($member);

    expect($result->status)->toBe(MemberActionStatus::Success)
        ->and($result->member)->toBe($member)
        ->and($result->account)->toBeNull()
        ->and($result->message)->toBeNull();
});

it('builds a last-owner-blocked result with a non-null message', function (): void {
    $result = MemberActionResult::lastOwnerBlocked();

    expect($result->status)->toBe(MemberActionStatus::LastOwnerBlocked)
        ->and($result->member)->toBeNull()
        ->and($result->message)->toBeString()
        ->and($result->message)->not->toBe('');
});

it('builds a not-member result', function (): void {
    $result = MemberActionResult::notMember();

    expect($result->status)->toBe(MemberActionStatus::NotMember)
        ->and($result->member)->toBeNull();
});

it('builds a forbidden result', function (): void {
    $result = MemberActionResult::forbidden();

    expect($result->status)->toBe(MemberActionStatus::Forbidden)
        ->and($result->member)->toBeNull();
});
