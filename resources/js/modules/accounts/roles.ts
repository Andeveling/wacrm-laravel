import type { MemberRole } from './contracts';

/** Role label shown in badges, picker, and the "Tu rol actual es X" footer. */
export const ROLE_LABEL: Record<MemberRole, string> = {
  owner: 'Owner',
  admin: 'Admin',
  member: 'Member',
  viewer: 'Viewer',
};

/** Badge variant per role — matches the `variant` prop on `@/components/ui/badge`. */
export const ROLE_BADGE: Record<
  MemberRole,
  'default' | 'secondary' | 'outline' | 'destructive'
> = {
  owner: 'default',
  admin: 'secondary',
  member: 'outline',
  viewer: 'outline',
};
