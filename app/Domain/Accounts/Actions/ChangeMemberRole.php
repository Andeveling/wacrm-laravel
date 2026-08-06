<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\MemberActionResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Domain\Accounts\Support\MembershipRules;
use App\Http\Requests\Accounts\ChangeMemberRoleRequest;
use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change the role of an existing Account member. Adheres to ADR 0001
 * (Action / Domain / Responder): this class owns HTTP shape — it
 * type-hints the FormRequest so role validation runs before we get
 * here — reads the facts the decision needs, and asks
 * {@see MembershipRules} for the verdict. The authorization matrix and
 * ADR 0002's Owner Protection live there, shared with RemoveMember and
 * InviteMember.
 *
 * The facts and the UPDATE run inside one transaction that opens by
 * locking the Account row. Locking the target's pivot would not be
 * enough: two concurrent demotions of two different Owners each lock a
 * different row, both read an Owner count of 2, and the Account ends
 * up with none. The Account row is the one thing every membership
 * mutation shares, so taking it serialises them.
 *
 * The `{member}` URL parameter is resolved by Laravel's implicit
 * binding into a {@see User}; the Account is bound via
 * `ensure.current-account` and the route's {account} placeholder.
 */
final readonly class ChangeMemberRole
{
    public function __construct(
        private MemberActionResponder $responder,
    ) {}

    public function __invoke(
        ChangeMemberRoleRequest $request,
        Account $account,
        User $member,
    ): mixed {
        $actor = $request->user();
        $newRole = $request->newRole();

        $result = DB::transaction(function () use ($account, $actor, $member, $newRole): MemberActionResult {
            Account::query()->whereKey($account->getKey())->lockForUpdate()->first();

            $status = MembershipRules::forRoleChange(
                $actor?->roleIn($account),
                $this->roleOf($account, $member),
                $newRole,
                $this->ownerCount($account),
            );

            if ($status === MemberActionStatus::Success) {
                $account->users()->updateExistingPivot($member->id, [
                    'role' => $newRole->value,
                ]);
            }

            return new MemberActionResult($status, account: $account);
        });

        return ($this->responder)(
            $result,
            flash: 'role_changed',
            toast: 'Rol actualizado.',
            route: 'accounts.members.index',
        );
    }

    /**
     * The role $member currently holds in $account, or null when they
     * are not a member at all.
     */
    private function roleOf(Account $account, User $member): ?AccountRole
    {
        return $account->users()->whereKey($member->id)->first()?->pivot->role;
    }

    private function ownerCount(Account $account): int
    {
        return $account->users()
            ->wherePivot('role', AccountRole::Owner->value)
            ->count();
    }
}
