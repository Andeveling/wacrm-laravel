import { mockContacts } from '@/lib/contacts/mock';
import type { Flow, FlowRun, FlowRunEvent } from '@/types';

export interface FlowTemplate {
  slug: string;
  name: string;
  description: string;
  nodeCount: number;
}

export const FLOW_TEMPLATES: FlowTemplate[] = [
  {
    slug: 'faq_bot',
    name: 'Preguntas frecuentes',
    description: 'Responde preguntas comunes con un menú de opciones.',
    nodeCount: 5,
  },
  {
    slug: 'lead_capture',
    name: 'Captura de leads',
    description: 'Recolecta nombre y correo antes de derivar a un agente.',
    nodeCount: 4,
  },
  {
    slug: 'appointment',
    name: 'Agendar cita',
    description: 'Guía al contacto para agendar una cita.',
    nodeCount: 6,
  },
];

export function mockFlows(): Flow[] {
  const statuses: Flow['status'][] = ['active', 'active', 'draft', 'archived'];
  const triggers: Flow['trigger_type'][] = [
    'keyword',
    'first_inbound_message',
    'manual',
    'keyword',
  ];
  const names = [
    'Bienvenida + FAQ',
    'Captura de leads',
    'Encuesta de satisfacción',
    'Flujo antiguo',
  ];
  return statuses.map((status, i) => ({
    id: `flow-${i}`,
    name: names[i],
    description: null,
    status,
    trigger_type: triggers[i],
    trigger_config:
      triggers[i] === 'keyword' ? { keywords: ['hola', 'info'] } : {},
    execution_count: [312, 87, 0, 12][i],
    last_executed_at:
      status === 'draft'
        ? null
        : new Date(Date.now() - i * 7_200_000).toISOString(),
    created_at: new Date(Date.now() - (i + 1) * 20 * 86_400_000).toISOString(),
    updated_at: new Date(Date.now() - i * 86_400_000).toISOString(),
  }));
}

export function mockFlowRuns(count = 15): {
  runs: FlowRun[];
  events: FlowRunEvent[];
} {
  const contacts = mockContacts(count);
  const statuses: FlowRun['status'][] = [
    'completed',
    'active',
    'handed_off',
    'timed_out',
    'failed',
  ];
  const runs: FlowRun[] = contacts.map((contact, i) => {
    const status = statuses[i % statuses.length];
    return {
      id: `run-${i}`,
      status,
      current_node_key: status === 'active' ? 'ask_email' : null,
      started_at: new Date(Date.now() - i * 3_600_000).toISOString(),
      ended_at:
        status === 'active'
          ? null
          : new Date(Date.now() - i * 3_500_000).toISOString(),
      reprompt_count: i % 3,
      contact: {
        id: contact.id,
        name: contact.name ?? null,
        phone: contact.phone,
      },
    };
  });
  const events: FlowRunEvent[] = runs.flatMap((run) => [
    {
      flow_run_id: run.id,
      event_type: 'started',
      node_key: 'start',
      created_at: run.started_at,
    },
    {
      flow_run_id: run.id,
      event_type: 'node_entered',
      node_key: 'ask_email',
      created_at: run.started_at,
    },
  ]);
  return { runs, events };
}
