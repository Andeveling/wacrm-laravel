import type { Contact } from './contacts';

export type ConversationStatus = 'open' | 'pending' | 'closed';

export interface Conversation {
  id: string;
  contact_id: string;
  contact?: Contact;
  status: ConversationStatus;
  last_message_text?: string;
  last_message_at?: string;
  unread_count: number;
  created_at: string;
  updated_at: string;
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
