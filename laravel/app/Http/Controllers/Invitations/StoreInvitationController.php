<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreInvitationController extends Controller
{
    /**
     * Create an invite link for the current account (admin+ only, mirroring
     * the Supabase RLS). The plaintext token is flashed exactly once as
     * `invitation_url`; only its SHA-256 hash is persisted, so the link can
     * never be resurfaced — dismissing it means revoke and re-issue.
     */
    public function __invoke(Request $request, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member,viewer'],
            'label' => ['nullable', 'string', 'max:80'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,365'],
        ]);

        $token = Str::random(43);

        Invitation::create([
            'token_hash' => hash('sha256', $token),
            'role' => $validated['role'],
            'invited_by' => $request->user()->id,
            'label' => $validated['label'] ?? null,
            'expires_at' => now()->addDays($validated['expires_in_days'] ?? 7),
        ]);

        return back()->with('invitation_url', route('invitations.preview', ['token' => $token]));
    }
}
