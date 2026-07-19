import { mockContacts } from '@/lib/contacts/mock';
import type { Automation, AutomationLog, AutomationTriggerType } from '@/types';

export interface AutomationTemplate {
  slug: string;
  name: string;
  description: string;
}

export const AUTOMATION_TEMPLATES: AutomationTemplate[] = [
  {
    slug: 'welcome_message',
    name: 'Mensaje de bienvenida',
    description: 'Responde automáticamente a contactos nuevos con un saludo.',
  },
  {
    slug: 'out_of_office',
    name: 'Fuera de oficina',
    description: 'Avisa cuando el equipo no está disponible.',
  },
  {
    slug: 'lead_qualifier',
    name: 'Calificador de prospectos',
    description: 'Hace preguntas para calificar un nuevo lead.',
  },
  {
    slug: 'follow_up_reminder',
    name: 'Recordatorio de seguimiento',
    description: 'Envía un recordatorio si no hay respuesta en 24h.',
  },
];

export function mockAutomations(): Automation[] {
  const triggers: AutomationTriggerType[] = [
    'first_inbound_message',
    'keyword_match',
    'tag_added',
    'time_based',
  ];
  return triggers.map((trigger_type, i) => ({
    id: `auto-${i}`,
    name: AUTOMATION_TEMPLATES[i].name,
    description: AUTOMATION_TEMPLATES[i].description,
    trigger_type,
    is_active: i !== 2,
    execution_count: [42, 128, 0, 7][i],
    last_executed_at:
      i === 2 ? null : new Date(Date.now() - i * 3_600_000).toISOString(),
    created_at: new Date(Date.now() - (i + 1) * 30 * 86_400_000).toISOString(),
    updated_at: new Date(Date.now() - i * 86_400_000).toISOString(),
  }));
}

export function mockAutomationLogs(
  automationId: string,
  count = 12,
): AutomationLog[] {
  const contacts = mockContacts(count);
  const statuses: AutomationLog['status'][] = [
    'success',
    'success',
    'partial',
    'failed',
  ];
  return contacts.map((contact, i) => {
    const status = statuses[i % statuses.length];
    return {
      id: `log-${automationId}-${i}`,
      automation_id: automationId,
      contact_id: contact.id,
      contact,
      trigger_event: 'first_inbound_message',
      status,
      error_message:
        status === 'failed'
          ? 'No se pudo enviar el mensaje: número inválido.'
          : undefined,
      steps_executed: [
        {
          step_id: 's1',
          step_type: 'send_message',
          status: status === 'failed' ? 'failed' : 'success',
          detail: 'Mensaje de bienvenida enviado',
        },
        {
          step_id: 's2',
          step_type: 'add_tag',
          status: status === 'partial' ? 'skipped' : 'success',
          detail: 'Etiqueta "nuevo" agregada',
        },
      ],
      created_at: new Date(Date.now() - i * 1_800_000).toISOString(),
    };
  });
}
