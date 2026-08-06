import type { MemberRole } from './contracts';
import { ROLE_LABEL } from './roles';

/** Shared toast for connection failures across every membership mutation. */
export const NETWORK_ERROR_MESSAGE = 'No se pudo conectar con el servidor.';

/** Three mutation contexts the hook centralises — each gets its own 403/generic toast. */
export type MembershipMutation =
  | 'change-role'
  | 'remove-member'
  | 'invite-member';

const FORBIDDEN_MESSAGE: Record<MembershipMutation, string> = {
  'change-role': 'No tienes permiso para cambiar roles.',
  'remove-member': 'No tienes permiso para remover miembros.',
  'invite-member': 'No tienes permiso para invitar miembros.',
};

const GENERIC_MESSAGE: Record<MembershipMutation, string> = {
  'change-role': 'No se pudo cambiar el rol.',
  'remove-member': 'No se pudo remover el miembro.',
  'invite-member': 'No se pudo crear la invitación.',
};

/** Maps an HTTP status (or `null` for network) to the user-facing toast per mutation context. */
export function errorMessageFor(
  context: MembershipMutation,
  status: number | null,
): string {
  if (status === null) {
    return NETWORK_ERROR_MESSAGE;
  }
  if (status === 403) {
    return FORBIDDEN_MESSAGE[context];
  }
  return GENERIC_MESSAGE[context];
}

/**
 * Single source of truth for the Owner Protection invariant (ADR 0002):
 * a member is the sole Owner iff they are an Owner AND the list has
 * exactly one Owner. Short-circuits as soon as a second Owner is found.
 */
export function isSoleOwner<T extends { id: number; role: MemberRole }>(
  member: T,
  members: readonly T[],
): boolean {
  if (member.role !== 'owner') {
    return false;
  }

  let ownerCount = 0;
  let matchesId = false;
  for (const candidate of members) {
    if (candidate.role !== 'owner') {
      continue;
    }
    ownerCount += 1;
    if (candidate.id === member.id) {
      matchesId = true;
    }
    if (ownerCount > 1) {
      return false;
    }
  }

  return ownerCount === 1 && matchesId;
}

/** Selectable option shape for the role picker. */
export type RoleOption = { value: MemberRole; label: string };

/** Role options for the picker — Owner is gated behind `isOwner` (Owner can promote to Owner). */
export function roleOptions(isOwner: boolean): RoleOption[] {
  const all: RoleOption[] = [
    { value: 'owner', label: ROLE_LABEL.owner },
    { value: 'admin', label: ROLE_LABEL.admin },
    { value: 'member', label: ROLE_LABEL.member },
    { value: 'viewer', label: ROLE_LABEL.viewer },
  ];

  return isOwner ? all : all.filter((opt) => opt.value !== 'owner');
}
