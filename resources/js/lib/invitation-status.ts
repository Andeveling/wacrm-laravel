/**
 * Mirrors the PHP enum `App\Models\Enums\InvitationStatus` — keep the
 * string values in sync if a new case is ever added server-side.
 */
export type InvitationStatus = 'valid' | 'used' | 'expired' | 'invalid';

/** Heading title + supporting paragraph per status. */
export interface InvitationStatusDisplay {
  title: string;
  description: string;
}

export const invitationStatusConfig: Record<
  InvitationStatus,
  InvitationStatusDisplay
> = {
  valid: {
    title: 'You have been invited',
    description: 'Review the details below, then register to join.',
  },
  used: {
    title: 'This invitation has already been used',
    description: 'Ask the person who sent it to issue a new one.',
  },
  expired: {
    title: 'This invitation has expired',
    description: 'Ask the person who sent it to issue a new one.',
  },
  invalid: {
    title: 'This invitation is not valid',
    description:
      'The link may have been revoked. Ask the person who sent it to issue a new one.',
  },
};

/** Tolerant lookup — falls back to `invalid` so unknown statuses still render a useful message. */
export function getInvitationStatus(status: string): InvitationStatusDisplay {
  return (
    invitationStatusConfig[status as InvitationStatus] ??
    invitationStatusConfig.invalid
  );
}
