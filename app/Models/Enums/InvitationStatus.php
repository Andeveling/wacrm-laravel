<?php

namespace App\Models\Enums;

/**
 * Public preview status the /join/{token} page exposes. Mirrors the
 * reasons returned by supabase peek_invitation, with `Invalid` also
 * covering unknown / revoked tokens (revoked lands in #16; UX-wise
 * "ask for a new invite" is the same answer either way).
 */
enum InvitationStatus: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Expired = 'expired';
    case Invalid = 'invalid';
}
