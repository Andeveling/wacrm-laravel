export type ApiScope =
  | 'messages:send'
  | 'messages:read'
  | 'contacts:read'
  | 'contacts:write'
  | 'conversations:read'
  | 'broadcasts:send'
  | 'webhooks:manage';

export interface ApiKey {
  id: string;
  name: string;
  key_prefix: string;
  scopes: ApiScope[];
  last_used_at: string | null;
  expires_at: string | null;
  revoked_at: string | null;
  created_at: string | null;
}
