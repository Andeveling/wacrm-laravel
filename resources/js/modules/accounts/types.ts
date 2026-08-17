export type Account = {
  id: string;
  name: string;
  type: 'personal' | 'team';
  created_at: string;
  updated_at: string;
};

export type MemberRole = 'owner' | 'admin' | 'member' | 'viewer';

export type AccountMember = {
  id: number;
  name: string;
  email: string;
  role: MemberRole;
  joined_at: string;
  is_you: boolean;
};
