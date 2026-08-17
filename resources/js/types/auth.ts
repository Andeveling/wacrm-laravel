export type User = {
  id: number;
  name: string;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  /* @chisel-2fa */
  two_factor_enabled?: boolean;
  /* @end-chisel-2fa */
  created_at: string;
  updated_at: string;
  [key: string]: unknown;
};

export type Auth = {
  user: User;
};

export type AccountType = 'personal' | 'team';

export type AccountRole = 'owner' | 'admin' | 'member' | 'viewer';

export type CurrentAccount = {
  id: string;
  name: string;
  type: AccountType;
  role: AccountRole;
};

export type AccountMembership = {
  id: string;
  name: string;
  type: AccountType;
};
