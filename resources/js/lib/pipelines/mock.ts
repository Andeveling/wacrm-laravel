import { mockContacts } from '@/lib/contacts/mock';
import type { Deal, Pipeline, PipelineStage } from '@/types';

export const MOCK_PIPELINE: Pipeline = {
  id: 'pipeline-1',
  name: 'Ventas',
  created_at: new Date().toISOString(),
};

export const MOCK_STAGES: PipelineStage[] = [
  {
    id: 'stage-new',
    pipeline_id: MOCK_PIPELINE.id,
    name: 'Nuevo prospecto',
    position: 0,
    color: '#3b82f6',
  },
  {
    id: 'stage-qualified',
    pipeline_id: MOCK_PIPELINE.id,
    name: 'Calificado',
    position: 1,
    color: '#eab308',
  },
  {
    id: 'stage-proposal',
    pipeline_id: MOCK_PIPELINE.id,
    name: 'Propuesta enviada',
    position: 2,
    color: '#f97316',
  },
  {
    id: 'stage-negotiation',
    pipeline_id: MOCK_PIPELINE.id,
    name: 'Negociación',
    position: 3,
    color: '#8b5cf6',
  },
  {
    id: 'stage-won',
    pipeline_id: MOCK_PIPELINE.id,
    name: 'Ganado',
    position: 4,
    color: '#22c55e',
  },
];

const TITLES = [
  'Licencia anual',
  'Implementación CRM',
  'Plan Pro x12',
  'Consultoría WhatsApp',
  'Renovación soporte',
];

export function mockDeals(count = 14): Deal[] {
  const contacts = mockContacts(count);
  return Array.from({ length: count }, (_, i) => ({
    id: `deal-${i}`,
    pipeline_id: MOCK_PIPELINE.id,
    stage_id: MOCK_STAGES[i % MOCK_STAGES.length].id,
    contact_id: contacts[i].id,
    contact: contacts[i],
    title: `${TITLES[i % TITLES.length]} — ${contacts[i].name}`,
    value: 500_000 + i * 350_000,
    currency: 'COP',
    status: i % 7 === 0 ? 'won' : i % 11 === 0 ? 'lost' : 'open',
    expected_close_date: new Date(
      Date.now() + (i + 1) * 5 * 86_400_000,
    ).toISOString(),
    created_at: new Date(Date.now() - i * 43_200_000).toISOString(),
    updated_at: new Date(Date.now() - i * 43_200_000).toISOString(),
  }));
}
