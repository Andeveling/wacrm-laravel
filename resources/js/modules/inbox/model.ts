import type { Conversation, Message } from './types';

export interface InboxSnapshot {
  conversations: Conversation[];
  messages: Message[];
}

export interface InboxMessagePayload {
  message: Message;
  conversation: Conversation;
}

export function applyInboxMessage(
  snapshot: InboxSnapshot,
  payload: InboxMessagePayload,
): InboxSnapshot {
  if (snapshot.messages.some((row) => row.id === payload.message.id)) {
    return snapshot;
  }

  const remaining = snapshot.conversations.filter(
    (row) => row.id !== payload.conversation.id,
  );

  return {
    conversations: [payload.conversation, ...remaining],
    messages: [...snapshot.messages, payload.message],
  };
}
