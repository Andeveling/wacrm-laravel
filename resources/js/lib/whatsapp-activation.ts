import type { AccountRole } from '@/types/auth';

const PRODUCT_PAGES = [
  'dashboard',
  'inbox',
  'contacts',
  'pipelines',
  'broadcasts',
  'automations',
  'flows',
  'agents',
  'notifications',
] as const;

const MANAGE_ROLES = new Set<AccountRole>(['owner', 'admin']);

export function isProductPage(component: string): boolean {
  return (PRODUCT_PAGES as readonly string[]).includes(component);
}

export function canConnectWhatsapp(role: AccountRole | undefined): boolean {
  return role !== undefined && MANAGE_ROLES.has(role);
}
