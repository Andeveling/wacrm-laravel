<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\InvitationStatus;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $account_id
 * @property string $token_hash
 * @property string $role
 * @property string|null $email
 * @property int|null $invited_by
 * @property string|null $label
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account_id', 'token_hash', 'role', 'email', 'invited_by', 'label', 'expires_at', 'accepted_at', 'accepted_by', 'revoked_at'])]
class Invitation extends Model
{
    protected $table = 'account_invitations';

    /** @use HasFactory<InvitationFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * Resolve the public preview status of an invitation looked up by its
     * token hash. The lookup bypasses the tenant scope (callers are
     * anonymous at /join/{token}) and returns a uniform shape so the route
     * doesn't have to interpret error rows.
     *
     * @return array{status: InvitationStatus, account_name: ?string, role: ?string, inviter_name: ?string, label: ?string, expires_at: ?Carbon}
     */
    public static function previewByTokenHash(string $tokenHash): array
    {
        $invitation = static::query()
            ->withoutGlobalScopes()
            ->with(['account', 'inviter'])
            ->where('token_hash', $tokenHash)
            ->first();

        if ($invitation === null) {
            return [
                'status' => InvitationStatus::Invalid,
                'account_name' => null,
                'role' => null,
                'inviter_name' => null,
                'label' => null,
                'expires_at' => null,
            ];
        }

        if ($invitation->revoked_at !== null) {
            return [
                'status' => InvitationStatus::Invalid,
                'account_name' => $invitation->account?->name,
                'role' => $invitation->role,
                'inviter_name' => $invitation->inviter?->name,
                'label' => $invitation->label,
                'expires_at' => $invitation->expires_at,
            ];
        }

        if ($invitation->accepted_at !== null) {
            return [
                'status' => InvitationStatus::Used,
                'account_name' => $invitation->account?->name,
                'role' => $invitation->role,
                'inviter_name' => $invitation->inviter?->name,
                'label' => $invitation->label,
                'expires_at' => $invitation->expires_at,
            ];
        }

        if ($invitation->expires_at->isPast()) {
            return [
                'status' => InvitationStatus::Expired,
                'account_name' => $invitation->account?->name,
                'role' => $invitation->role,
                'inviter_name' => $invitation->inviter?->name,
                'label' => $invitation->label,
                'expires_at' => $invitation->expires_at,
            ];
        }

        return [
            'status' => InvitationStatus::Valid,
            'account_name' => $invitation->account?->name,
            'role' => $invitation->role,
            'inviter_name' => $invitation->inviter?->name,
            'label' => $invitation->label,
            'expires_at' => $invitation->expires_at,
        ];
    }

    /**
     * Whether this invitation can still be redeemed: not revoked, not
     * already accepted, and not past its expiration.
     */
    public function isRedeemable(): bool
    {
        return $this->revoked_at === null
            && $this->accepted_at === null
            && ! $this->expires_at->isPast();
    }

    /**
     * The account this invitation is for.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The user who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * The user who accepted the invitation, once redeemed.
     *
     * @return BelongsTo<User, $this>
     */
    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
