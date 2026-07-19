import { mockContacts } from '@/lib/contacts/mock';
import type { Conversation, Message } from '@/types';

const LAST_MESSAGES = [
  '¿Tienen disponibilidad para mañana?',
  'Perfecto, muchas gracias.',
  '¿Cuál es el precio del plan Pro?',
  'Ya realicé el pago, ¿pueden confirmar?',
  'Necesito ayuda con mi pedido #4821.',
];

export function mockConversations(): Conversation[] {
  const contacts = mockContacts(16);
  const statuses: Conversation['status'][] = [
    'open',
    'open',
    'pending',
    'closed',
  ];
  return contacts.map((contact, i) => ({
    id: `conv-${i}`,
    contact_id: contact.id,
    contact,
    status: statuses[i % statuses.length],
    last_message_text: LAST_MESSAGES[i % LAST_MESSAGES.length],
    last_message_at: new Date(Date.now() - i * 1_800_000).toISOString(),
    unread_count: i % 3 === 0 ? i % 5 : 0,
    created_at: new Date(Date.now() - i * 86_400_000).toISOString(),
    updated_at: new Date(Date.now() - i * 1_800_000).toISOString(),
  }));
}

export function mockMessages(conversationId: string): Message[] {
  const script: { sender: Message['sender_type']; text: string }[] = [
    { sender: 'customer', text: 'Hola, buenas tardes.' },
    { sender: 'agent', text: '¡Hola! ¿En qué puedo ayudarte hoy?' },
    { sender: 'customer', text: '¿Tienen disponibilidad para mañana?' },
    {
      sender: 'agent',
      text: 'Sí, tenemos espacio en la mañana. ¿Te confirmo a las 10am?',
    },
    { sender: 'customer', text: 'Perfecto, muchas gracias.' },
  ];
  return script.map((m, i) => ({
    id: `${conversationId}-msg-${i}`,
    conversation_id: conversationId,
    sender_type: m.sender,
    content_text: m.text,
    status:
      m.sender === 'agent'
        ? i === script.length - 1
          ? 'delivered'
          : 'read'
        : 'read',
    created_at: new Date(
      Date.now() - (script.length - i) * 900_000,
    ).toISOString(),
  }));
}
