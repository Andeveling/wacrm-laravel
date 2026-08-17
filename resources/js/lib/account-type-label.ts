import type { AccountType } from '@/types/auth';

export function accountTypeLabel(type: AccountType): string {
  return type === 'personal' ? 'Personal' : 'Equipo';
}
