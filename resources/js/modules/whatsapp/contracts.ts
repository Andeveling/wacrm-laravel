export type WhatsappReadiness =
  | 'credentials_verified'
  | 'subscribed'
  | 'webhook_waiting'
  | 'active'
  | 'attention_required'
  | 'disconnected';

export type WhatsappHealth =
  | 'healthy'
  | 'pending'
  | 'attention'
  | 'disconnected';

export type WhatsappConnection = {
  id: string;
  phone_number_id: string | null;
  waba_id: string | null;
  readiness: WhatsappReadiness;
  is_default: boolean;
  pending_default?: boolean;
  connected_at: string | null;
  registered_at: string | null;
  last_registration_error: string | null;
  last_failure?: string | null;
  health?: WhatsappHealth;
};

export type WhatsappRemediationKind =
  | 'missing_legacy_connection'
  | 'ambiguous_conversation_connection'
  | 'waba_claimed_by_another_account'
  | 'phone_number_claimed_by_another_account'
  | 'incomplete_legacy_config';

export type WhatsappRemediationVariant = 'assign' | 'acknowledge';

export type WhatsappRemediationIssue = {
  id: string;
  kind: WhatsappRemediationKind | string;
  conversation_id: string | null;
  contact_name: string | null;
  action: string | null;
  candidate_connections: number | null;
};

export type WhatsappConnectFormData = {
  phone_number_id: string;
  waba_id: string;
  access_token: string;
  pin: string;
  confirm_default: boolean;
};

export type WhatsappSettingsPageProps = {
  canManage: boolean;
  connections: WhatsappConnection[];
  /** Wire name from Inertia. New TypeScript identifiers say Remediation. */
  legacyIssues: WhatsappRemediationIssue[];
  webhookUrl: string;
  verifyToken?: string | null;
  status?: string | null;
  error?: string | null;
};
