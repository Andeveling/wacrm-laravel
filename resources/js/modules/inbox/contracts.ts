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
