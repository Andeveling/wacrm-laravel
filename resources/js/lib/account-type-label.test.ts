import { describe, expect, it } from 'vitest';
import { accountTypeLabel } from './account-type-label';

describe('accountTypeLabel', () => {
  it('labels a personal account as Personal', () => {
    expect(accountTypeLabel('personal')).toBe('Personal');
  });

  it('labels a team account as Equipo', () => {
    expect(accountTypeLabel('team')).toBe('Equipo');
  });
});
