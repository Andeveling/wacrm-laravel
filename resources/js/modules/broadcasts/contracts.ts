export interface BroadcastContact {
  id: string;
  phone: string;
  name?: string;
}

export type BroadcastStatus =
  | 'draft'
  | 'scheduled'
  | 'sending'
  | 'sent'
  | 'failed'
  | 'paused';
export type RecipientStatus =
  | 'pending'
  | 'sent'
  | 'delivered'
  | 'read'
  | 'replied'
  | 'failed';

export interface Broadcast {
  id: string;
  connection_id?: string | null;
  name: string;
  template_name: string;
  template_language: string;
  scheduled_at?: string;
  status: BroadcastStatus;
  total_recipients: number;
  sent_count: number;
  delivered_count: number;
  read_count: number;
  replied_count: number;
  failed_count: number;
  created_at: string;
}

export interface BroadcastRecipient {
  id: string;
  broadcast_id: string;
  contact_id: string | null;
  contact?: BroadcastContact;
  status: RecipientStatus;
  sent_at?: string;
  delivered_at?: string;
  read_at?: string;
  error_message?: string;
}

export type MessageTemplateCategory =
  | 'Marketing'
  | 'Utility'
  | 'Authentication';

export type MessageTemplateStatus =
  | 'DRAFT'
  | 'PENDING'
  | 'APPROVED'
  | 'REJECTED'
  | 'PAUSED'
  | 'DISABLED'
  | 'IN_APPEAL'
  | 'PENDING_DELETION';

export interface MessageTemplate {
  id: string;
  name: string;
  category: MessageTemplateCategory;
  language?: string;
  body_text: string;
  footer_text?: string;
  status?: MessageTemplateStatus;
  rejection_reason?: string;
}

export interface BroadcastTag {
  id: string;
  name: string;
  color: string;
}

export interface BroadcastConnection {
  id: string;
  phone_number_id: string;
  is_default: boolean;
}
