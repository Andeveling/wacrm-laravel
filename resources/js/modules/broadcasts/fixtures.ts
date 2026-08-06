import type {
  Broadcast,
  BroadcastRecipient,
  MessageTemplate,
} from './contracts';

export const AUDIENCE_TAGS = [
  { id: 'tag-vip', name: 'VIP', color: '#f59e0b' },
  { id: 'tag-lead', name: 'Prospecto', color: '#3b82f6' },
  { id: 'tag-client', name: 'Cliente', color: '#10b981' },
];

export function mockAudienceContacts(count = 24) {
  return Array.from({ length: count }, (_, index) => ({
    id: `broadcast-contact-${index}`,
    name: `Contacto ${index + 1}`,
    phone: `+57 301 000 ${String(index).padStart(4, '0')}`,
    tags: AUDIENCE_TAGS.filter((_, tagIndex) => (index + tagIndex) % 3 === 0),
  }));
}

export const MOCK_TEMPLATES: MessageTemplate[] = [
  {
    id: 'tpl-promo',
    name: 'promo_julio',
    category: 'Marketing',
    language: 'es_CO',
    body_text: 'Hola {{1}}, tenemos una promoción especial para ti este mes.',
    footer_text: 'Responde STOP para dejar de recibir promociones.',
    status: 'APPROVED',
  },
  {
    id: 'tpl-reminder',
    name: 'recordatorio_cita',
    category: 'Utility',
    language: 'es_CO',
    body_text: 'Hola {{1}}, te recordamos tu cita el {{2}}.',
    status: 'APPROVED',
  },
  {
    id: 'tpl-otp',
    name: 'codigo_verificacion',
    category: 'Authentication',
    language: 'es_CO',
    body_text: 'Tu código de verificación es {{1}}.',
    status: 'APPROVED',
  },
];

export function mockBroadcasts(): Broadcast[] {
  const statuses: Broadcast['status'][] = [
    'sent',
    'sent',
    'sending',
    'scheduled',
    'draft',
    'failed',
  ];
  return statuses.map((status, i) => {
    const total = 200 + i * 47;
    const sent = status === 'draft' ? 0 : total;
    const delivered =
      status === 'draft' || status === 'scheduled'
        ? 0
        : Math.round(sent * 0.94);
    const read =
      status === 'sent'
        ? Math.round(delivered * 0.72)
        : Math.round(delivered * 0.3);
    return {
      id: `bc-${i}`,
      name: `Difusión ${MOCK_TEMPLATES[i % MOCK_TEMPLATES.length].name} #${i + 1}`,
      template_name: MOCK_TEMPLATES[i % MOCK_TEMPLATES.length].name,
      template_language: 'es_CO',
      status,
      total_recipients: total,
      sent_count: sent,
      delivered_count: delivered,
      read_count: read,
      replied_count: Math.round(read * 0.2),
      failed_count: status === 'failed' ? Math.round(total * 0.15) : 0,
      created_at: new Date(Date.now() - i * 2 * 86_400_000).toISOString(),
    };
  });
}

export function mockRecipients(
  broadcastId: string,
  count = 30,
): BroadcastRecipient[] {
  const contacts = mockAudienceContacts(count);
  const statuses: BroadcastRecipient['status'][] = [
    'delivered',
    'read',
    'replied',
    'sent',
    'pending',
    'failed',
  ];
  return contacts.map((contact, i) => ({
    id: `rec-${broadcastId}-${i}`,
    broadcast_id: broadcastId,
    contact_id: contact.id,
    contact,
    status: statuses[i % statuses.length],
    sent_at: new Date(Date.now() - i * 3_600_000).toISOString(),
    delivered_at:
      i % 6 !== 4 && i % 6 !== 5
        ? new Date(Date.now() - i * 3_500_000).toISOString()
        : undefined,
    read_at:
      i % 6 === 1 || i % 6 === 2
        ? new Date(Date.now() - i * 3_000_000).toISOString()
        : undefined,
    error_message: i % 6 === 5 ? 'Número no válido' : undefined,
  }));
}
