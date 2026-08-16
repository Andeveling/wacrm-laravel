import type {
  ActivityItem,
  ConversationsSeriesPoint,
  MetricsBundle,
  PipelineDonutData,
  ResponseTimeSummary,
} from '../types';
import { lastNDayKeys } from './date-utils';

/** Static placeholder data — this page has no backend wired up yet. */
export function mockMetrics(): MetricsBundle {
  return {
    activeConversations: { current: 42, previous: 3 },
    newContactsToday: { current: 7, previous: 4 },
    openDealsValue: 18_500_000,
    openDealsCount: 12,
    messagesSentToday: { current: 128, previous: 96 },
  };
}

export function mockConversationsSeries(
  days: number,
): ConversationsSeriesPoint[] {
  return lastNDayKeys(days).map((day, i) => ({
    day,
    incoming: Math.round(8 + 6 * Math.sin(i / 2) + (i % 3) * 2),
    outgoing: Math.round(6 + 5 * Math.cos(i / 3) + (i % 4)),
  }));
}

export function mockPipelineDonut(): PipelineDonutData {
  const stages = [
    {
      id: 'new',
      name: 'Nuevo',
      color: '#3b82f6',
      dealCount: 5,
      totalValue: 6_000_000,
    },
    {
      id: 'qualified',
      name: 'Calificado',
      color: '#7c3aed',
      dealCount: 3,
      totalValue: 5_500_000,
    },
    {
      id: 'proposal',
      name: 'Propuesta',
      color: '#f59e0b',
      dealCount: 2,
      totalValue: 4_000_000,
    },
    {
      id: 'won',
      name: 'Ganado',
      color: '#10b981',
      dealCount: 2,
      totalValue: 3_000_000,
    },
  ];
  return {
    stages,
    totalValue: stages.reduce((sum, s) => sum + s.totalValue, 0),
  };
}

export function mockResponseTime(): ResponseTimeSummary {
  const buckets = [4.2, 3.1, 5.6, 2.8, 6.4, 1.5, 2.2].map(
    (avgMinutes, dow) => ({
      dow,
      avgMinutes,
      samples: 10 + dow,
    }),
  );
  return { buckets, thisWeekAvg: 3.7, lastWeekAvg: 4.1 };
}

export function mockActivity(count: number): ActivityItem[] {
  const kinds: ActivityItem['kind'][] = [
    'message',
    'contact',
    'deal',
    'broadcast',
    'automation',
  ];
  const texts: Record<ActivityItem['kind'], string> = {
    message: 'Nuevo mensaje de Laura Gómez',
    contact: 'Nuevo contacto: Carlos Ruiz',
    deal: 'Negocio movido a "Propuesta"',
    broadcast: 'Difusión "Promo julio" enviada',
    automation: 'Automatización "Bienvenida" ejecutada',
  };
  return Array.from({ length: count }, (_, i) => {
    const kind = kinds[i % kinds.length];
    return {
      id: `activity-${i}`,
      kind,
      text: texts[kind],
      at: new Date(Date.now() - i * 15 * 60 * 1000).toISOString(),
    };
  });
}
