export type NotificationType = 'conversation_assigned';

export interface Notification {
  id: string;
  type: NotificationType;
  conversation_id?: string;
  title: string;
  body?: string;
  read_at?: string;
  created_at: string;
}
