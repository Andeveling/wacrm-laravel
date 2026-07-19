<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Responders\ChangeMemberRoleResponder;
use App\Domain\Accounts\Results\MemberActionResult;
use App\Domain\Accounts\Support\MemberActionStatus;
use App\Http\Requests\Accounts\ChangeMemberRoleRequest;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Change the role of an existing Account member. Adheres to ADR 0001
 * (Action / Domain / Responder): this class owns HTTP shape — it
 * type-hints the FormRequest so role validation runs before we get
 * here — and delegates business rules (auth, pivot lookup, Owner
 * Protection) to the inlined Domain code, returning a
 * {@see MemberActionResult} for the Responder to map.
 *
 * Authorization matrix (cross-issue contract):
 *  - Owner:    may mutate any member's role to any value (subject
 *              to ADR 0002 sole-Owner block).
 *  - Admin:    may mutate any NON-Owner member's role to any
 *              NON-Owner value. Touching an Owner row or promoting
 *              to Owner is Forbidden so an Admin cannot escalate
 *              themselves nor weaken accountability.
 *  - Member / Viewer: Forbidden for any mutation.
 *
 * ADR 0002 — Owner Protection. The "is the target the last Owner"
 * check happens BEFORE the UPDATE; on a hit we return
 * LastOwnerBlocked with no DB write. The check + update run inside a
 * single transaction with a row lock on the target pivot so two
 * concurrent role changes cannot race past the count check.
 *
 * The `{member}` URL parameter is resolved by Laravel's implicit
 * binding into a {@see User}; the Account is bound via
 * `ensure.current-account` and the route's {account} placeholder.
 */
final readonly class ChangeMemberRole
{
    public function __construct(
        private ChangeMemberRoleResponder $responder,
    ) {}

    public function __invoke(
        ChangeMemberRoleRequest $request,
        Account $account,
        User $member,
    ): mixed {
        $result = $this->execute(
            $request->user(),
            $account,
            $member,
            $request->newRole(),
        );

        return ($this->responder)($result);
    }

    /**
     * Pure domain logic — no Request, no HTTP, return value is a
     * MemberActionResult. The Account is attached to every outcome
     * (even Forbidden / NotMember) so the Responder can redirect to
     * the members page on LastOwnerBlocked without re-querying.
     */
    private function execute(
        ?User $actor,
        Account $account,
        User $target,
        AccountRole $newRole,
    ): MemberActionResult {
        $actorRole = $actor?->roleIn($account);

        // No membership at all — top-level contract treats anonymous
        // and non-member actors the same way: 403 via the Responder.
        if ($actorRole === null) {
            return new MemberActionResult(
                MemberActionStatus::Forbidden,
                account: $account,
            );
        }

        // Resolve target's pivot row inside a transaction so the
        // Owner-count check + UPDATE happen as one atomic step. The
        // closure captures $actorRole so the Admin-tier guard can
        // run inside the same transaction as the lookup.
        return DB::transaction(function () use ($account, $target, $newRole, $actorRole): MemberActionResult {
            $pivot = $account->users()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->first();

            if ($pivot === null) {
                return new MemberActionResult(
                    MemberActionStatus::NotMember,
                    account: $account,
                );
            }

            $currentRole = $this->normalizeRole($pivot->pivot->role);

            // ADR 0002 — Owner Protection. Only when the target
            // would be leaving the Owner tier AND they are the last
            // Owner do we refuse. Promoting a new Owner first is
            // the legal way to "drop" the Owner role.
            if ($currentRole === AccountRole::Owner
                && $newRole !== AccountRole::Owner
                && $this->ownerCount($account) === 1) {
                return new MemberActionResult(
                    MemberActionStatus::LastOwnerBlocked,
                    account: $account,
                );
            }

            // Cross-issue contract: role-tier floor is Admin. Member
            // and Viewer actors cannot mutate roles at all.
            if ($actorRole !== AccountRole::Owner && $actorRole !== AccountRole::Admin) {
                return new MemberActionResult(
                    MemberActionStatus::Forbidden,
                    account: $account,
                );
            }

            // Cross-issue contract: Admins manage members but cannot
            // touch Owner rows or promote anyone to Owner. Owner-tier
            // mutations are reserved for Owner actors so an Admin
            // cannot escalate themselves nor weaken accountability.
            if ($actorRole === AccountRole::Admin
                && ($currentRole === AccountRole::Owner || $newRole === AccountRole::Owner)) {
                return new MemberActionResult(
                    MemberActionStatus::Forbidden,
                    account: $account,
                );
            }

            $account->users()->updateExistingPivot($target->id, [
                'role' => $newRole->value,
            ]);

            // Re-fetch the pivot so the result carries the new role
            // and the updated joined_at from the row we just touched.
            $updated = $account->users()->whereKey($target->id)->first();

            $pivotRow = new AccountUser([
                'account_id' => $account->id,
                'user_id' => $target->id,
                'role' => $newRole,
                'joined_at' => $updated?->pivot->joined_at,
            ]);

            return new MemberActionResult(
                MemberActionStatus::Success,
                member: $pivotRow,
                account: $account,
            );
        });
    }

    private function ownerCount(Account $account): int
    {
        return $account->users()
            ->wherePivot('role', AccountRole::Owner->value)
            ->count();
    }

    private function normalizeRole(mixed $role): AccountRole
    {
        if ($role instanceof AccountRole) {
            return $role;
        }

        return AccountRole::from((string) $role);
    }
}
