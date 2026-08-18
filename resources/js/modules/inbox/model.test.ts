import { describe, expect, it } from 'vitest';
import { applyInboxMessage } from './model';
import type { Conversation, Message } from './types';

function conversation(overrides: Partial<Conversation> = {}): Conversation {
  return {
    id: 'conv-1',
    contact_id: 'contact-1',
    connection_id: 'conn-1',
    contact: {
      id: 'contact-1',
      name: 'Ana Pérez',
      phone: '+573001112233',
    },
    status: 'open',
    last_message_text: 'Antes',
    last_message_at: '2026-08-17T10:00:00.000Z',
    unread_count: 0,
    created_at: '2026-08-17T09:00:00.000Z',
    updated_at: '2026-08-17T10:00:00.000Z',
    ...overrides,
  };
}

function message(overrides: Partial<Message> = {}): Message {
  return {
    id: 'msg-1',
    conversation_id: 'conv-1',
    sender_type: 'customer',
    content_text: 'Hola ahora',
    status: 'delivered',
    created_at: '2026-08-17T10:05:00.000Z',
    ...overrides,
  };
}

describe('applyInboxMessage', () => {
  it('updates preview, time and unread for an existing conversation', () => {
    const incoming = conversation({
      last_message_text: 'Hola ahora',
      last_message_at: '2026-08-17T10:05:00.000Z',
      unread_count: 1,
    });

    const next = applyInboxMessage(
      { conversations: [conversation()], messages: [] },
      { message: message(), conversation: incoming },
    );

    expect(next.conversations[0]?.last_message_text).toBe('Hola ahora');
    expect(next.conversations[0]?.unread_count).toBe(1);
    expect(next.messages).toHaveLength(1);
  });

  it('inserts a new conversation at the top', () => {
    const existing = conversation({ id: 'conv-old' });
    const incoming = conversation({
      id: 'conv-new',
      last_message_text: 'Primera',
      unread_count: 1,
    });

    const next = applyInboxMessage(
      { conversations: [existing], messages: [] },
      {
        message: message({
          id: 'msg-new',
          conversation_id: 'conv-new',
          content_text: 'Primera',
        }),
        conversation: incoming,
      },
    );

    expect(next.conversations.map((row) => row.id)).toEqual([
      'conv-new',
      'conv-old',
    ]);
  });

  it('does not apply the same message id twice', () => {
    const snapshot = {
      conversations: [conversation({ unread_count: 1 })],
      messages: [message()],
    };

    const next = applyInboxMessage(snapshot, {
      message: message(),
      conversation: conversation({ unread_count: 2, last_message_text: 'dup' }),
    });

    expect(next.messages).toHaveLength(1);
    expect(next.conversations[0]?.unread_count).toBe(1);
    expect(next.conversations[0]?.last_message_text).toBe('Antes');
  });
});
