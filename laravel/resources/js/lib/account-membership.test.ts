import { describe, expect, it } from 'vitest';
import type { AccountMember } from '@/components/accounts/member-management';
import {
  errorMessageFor,
  isSoleOwner,
  NETWORK_ERROR_MESSAGE,
  roleOptions,
} from '@/lib/account-membership';

const member = (id: number, role: AccountMember['role']): AccountMember => ({
  id,
  name: `User ${id}`,
  email: `user${id}@example.com`,
  role,
  joined_at: '2026-01-01T00:00:00Z',
  is_you: false,
});

describe('isSoleOwner (ADR 0002 — Owner Protection invariant)', () => {
  it('returns true when the member is the only Owner in the list', () => {
    const owner = member(1, 'owner');
    const admin = member(2, 'admin');
    const viewer = member(3, 'viewer');

    expect(isSoleOwner(owner, [owner, admin, viewer])).toBe(true);
  });

  it('returns false when there are multiple Owners', () => {
    const ownerA = member(1, 'owner');
    const ownerB = member(2, 'owner');

    expect(isSoleOwner(ownerA, [ownerA, ownerB])).toBe(false);
    expect(isSoleOwner(ownerB, [ownerA, ownerB])).toBe(false);
  });

  it('returns false when the member is an Owner but other Owners exist in the list', () => {
    const owner = member(1, 'owner');
    const admin = member(2, 'admin');
    const secondOwner = member(3, 'owner');

    expect(isSoleOwner(owner, [owner, admin, secondOwner])).toBe(false);
  });

  it('returns false when the member is not an Owner', () => {
    const owner = member(1, 'owner');
    const admin = member(2, 'admin');

    expect(isSoleOwner(admin, [owner, admin])).toBe(false);
  });

  it('returns false when the list is empty (vacuously nobody is the sole Owner)', () => {
    const owner = member(1, 'owner');

    expect(isSoleOwner(owner, [])).toBe(false);
  });

  it('treats the sole Owner correctly even when the member is not the first element', () => {
    const admin = member(1, 'admin');
    const viewer = member(2, 'viewer');
    const soleOwner = member(3, 'owner');

    expect(isSoleOwner(soleOwner, [admin, viewer, soleOwner])).toBe(true);
  });

  it('returns false when the member is not in the list at all', () => {
    const orphanOwner = member(99, 'owner');
    const admin = member(2, 'admin');

    expect(isSoleOwner(orphanOwner, [admin])).toBe(false);
  });
});

describe('errorMessageFor', () => {
  it('returns the forbidden message for a 403 on change-role', () => {
    expect(errorMessageFor('change-role', 403)).toBe(
      'No tenés permiso para cambiar roles.',
    );
  });

  it('returns the forbidden message for a 403 on remove-member', () => {
    expect(errorMessageFor('remove-member', 403)).toBe(
      'No tenés permiso para remover miembros.',
    );
  });

  it('returns the forbidden message for a 403 on invite-member', () => {
    expect(errorMessageFor('invite-member', 403)).toBe(
      'No tenés permiso para invitar miembros.',
    );
  });

  it('returns a context-specific generic message for non-403 HTTP errors', () => {
    expect(errorMessageFor('change-role', 500)).toBe(
      'No se pudo cambiar el rol.',
    );
    expect(errorMessageFor('remove-member', 422)).toBe(
      'No se pudo remover el miembro.',
    );
    expect(errorMessageFor('invite-member', 422)).toBe(
      'No se pudo crear la invitación.',
    );
  });

  it('returns the network message when status is null', () => {
    expect(errorMessageFor('change-role', null)).toBe(NETWORK_ERROR_MESSAGE);
    expect(errorMessageFor('remove-member', null)).toBe(NETWORK_ERROR_MESSAGE);
    expect(errorMessageFor('invite-member', null)).toBe(NETWORK_ERROR_MESSAGE);
  });

  it('does not confuse a 403 with the generic branch', () => {
    expect(errorMessageFor('change-role', 403)).not.toBe(
      'No se pudo cambiar el rol.',
    );
    expect(errorMessageFor('remove-member', 403)).not.toBe(
      'No se pudo remover el miembro.',
    );
  });
});

describe('roleOptions', () => {
  it('returns all four roles with their labels when the viewer is an Owner', () => {
    const options = roleOptions(true);

    expect(options).toEqual([
      { value: 'owner', label: 'Owner' },
      { value: 'admin', label: 'Admin' },
      { value: 'member', label: 'Member' },
      { value: 'viewer', label: 'Viewer' },
    ]);
  });

  it('omits the Owner option when the viewer is not an Owner', () => {
    const options = roleOptions(false);

    expect(options.map((o) => o.value)).toEqual(['admin', 'member', 'viewer']);
  });

  it('preserves order regardless of isOwner', () => {
    expect(roleOptions(true).map((o) => o.value)).toEqual([
      'owner',
      'admin',
      'member',
      'viewer',
    ]);
    expect(roleOptions(false).map((o) => o.value)).toEqual([
      'admin',
      'member',
      'viewer',
    ]);
  });
});
