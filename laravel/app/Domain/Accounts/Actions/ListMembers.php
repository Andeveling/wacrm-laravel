<?php

namespace App\Domain\Accounts\Actions;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the read-only members roster for the given account. The page
 * is reachable by any member (viewer included) — mutation affordances
 * are gated client-side by is_admin / is_owner and server-side by the
 * respective Actions (#44, #45, #46). The role of the requester comes
 * from the pivot via User::roleIn() so we don't reach into the session
 * twice (CurrentAccount already binds the same role, but routing via
 * the user keeps the Action independently testable).
 *
 * ADR 0001 rule 4: render-only endpoints skip the Responder layer and
 * call Inertia::render directly. This endpoint has no business rules
 * worth extracting — a single SELECT plus Inertia props.
 */
final readonly class ListMembers
{
    public function __invoke(Request $request, Account $account): Response
    {
        $user = $request->user();
        $viewerRole = $user->roleIn($account);

        abort_if($viewerRole === null, 403);

        $members = $account->users()
            ->orderBy('users.name')
            ->get()
            ->map(fn ($member) => [
                'id' => (int) $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role instanceof AccountRole
                    ? $member->pivot->role->value
                    : (string) $member->pivot->role,
                'joined_at' => optional($member->pivot->joined_at)->toIso8601String(),
                'is_you' => (int) $member->id === (int) $user->id,
            ])
            ->all();

        return Inertia::render('Accounts/Members', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'role' => $viewerRole->value,
            ],
            'members' => $members,
            'is_owner' => $viewerRole === AccountRole::Owner,
            'is_admin' => $viewerRole->atLeast(AccountRole::Admin),
        ]);
    }
}
