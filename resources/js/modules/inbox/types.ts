export interface ConversationContact {
  id: string;
  phone: string;
  name?: string;
  email?: string;
  company?: string;
  tags?: Array<{ id: string; name: string; color: string }>;
}

export type ConversationStatus = 'open' | 'pending' | 'closed';

export interface Conversation {
  id: string;
  contact_id: string;
  connection_id?: string | null;
  contact?: ConversationContact;
  status: ConversationStatus;
  last_message_text?: string;
  last_message_at?: string;
  unread_count: number;
  created_at: string;
  updated_at: string;
}

export interface InboxPageProps {
  conversations: Conversation[];
  messages: Message[];
  contacts: InboxContact[];
  connections: InboxConnection[];
  can_mark_seen: boolean;
}

export interface InboxContact {
  id: string;
  name?: string | null;
  phone: string;
}

export interface InboxConnection {
  id: string;
  phone_number_id: string;
  is_default: boolean;
}

export type SenderType = 'customer' | 'agent' | 'bot';
export type MessageStatus =
  | 'sending'
  | 'sent'
  | 'delivered'
  | 'read'
  | 'failed';

export interface Message {
  id: string;
  conversation_id: string;
  sender_type: SenderType;
  content_text: string;
  status: MessageStatus;
  created_at: string;
}

export type ThreadMessage = Message & {
  timeout_failed?: boolean;
};

export const INBOX_TIMEOUT_COPY =
  'El contacto puede haber recibido el WhatsApp. Reintentar puede duplicarlo.';
