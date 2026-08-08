<?php

namespace App\Domain\Accounts\Actions;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Invitation;
use App\Models\User;
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

        $viewerId = (int) $user->id;
        $isAdmin = $viewerRole->atLeast(AccountRole::Admin);
        $members = $account->users()
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $member): array => $this->row($member, $viewerId))
            ->all();

        $props = [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'role' => $viewerRole->value,
            ],
            'members' => $members,
            'is_owner' => $viewerRole === AccountRole::Owner,
            'is_admin' => $isAdmin,
        ];

        if ($isAdmin) {
            $props['invitations'] = Invitation::query()
                ->with('inviter:id,name')
                ->where('account_id', $account->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->orderByDesc('expires_at')
                ->get()
                ->map(fn (Invitation $invitation): array => $this->invitationRow($invitation))
                ->all();
        }

        return Inertia::render('accounts/members', $props);
    }

    /**
     * Project a BelongsToMany User row into the Inertia payload shape.
     * The pivot is resolved via {@see User::getRelationValue()} so PHPStan
     * sees it as a typed {@see AccountUser} (the dynamic pivot attribute
     * is invisible to static analysis otherwise).
     *
     * @return array{id: int, name: string, email: string, role: string, joined_at: ?string, is_you: bool}
     */
    private function row(User $member, int $viewerId): array
    {
        /** @var AccountUser $pivot */
        $pivot = $member->getRelationValue('pivot');

        return [
            'id' => (int) $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $pivot->role instanceof AccountRole
                ? $pivot->role->value
                : (string) $pivot->role,
            'joined_at' => optional($pivot->joined_at)->toIso8601String(),
            'is_you' => (int) $member->id === $viewerId,
        ];
    }

    /**
     * @return array{id: string, email: string|null, role: string, inviter: string, created_at: string|null, expires_at: string, status: string}
     */
    private function invitationRow(Invitation $invitation): array
    {
        $inviter = $invitation->getRelation('inviter');

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'inviter' => $inviter instanceof User ? $inviter->name : 'Unknown',
            'created_at' => $invitation->created_at?->toIso8601String(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'status' => $invitation->expires_at->isFuture() ? 'Active' : 'Expired',
        ];
    }
}
