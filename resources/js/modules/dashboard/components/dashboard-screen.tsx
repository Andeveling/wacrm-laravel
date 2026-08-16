import { Head, usePage } from '@inertiajs/react';
import { DollarSign, MessageSquare, Send, UserPlus } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import type { ConversationsSeriesPoint } from '../types';
import { ActivityFeed } from './activity-feed';
import { ConversationsChart } from './conversations-chart';
import { MetricCard } from './metric-card';
import { PipelineDonut } from './pipeline-donut';
import { QuickActions } from './quick-actions';
import { ResponseTimeChart } from './response-time-chart';

type RangeDays = 7 | 30 | 90;

export default function Dashboard() {
  const props = usePage().props as unknown as {
    metrics: {
      activeConversations: { current: number; previous: number };
      newContactsToday: { current: number; previous: number };
      openDealsValue: number;
      openDealsCount: number;
      messagesSentToday: { current: number; previous: number };
    };
    conversationsSeries: Record<
      RangeDays,
      { day: string; incoming: number; outgoing: number }[]
    >;
    pipeline: {
      stages: {
        id: string;
        name: string;
        color: string;
        dealCount: number;
        totalValue: number;
      }[];
      totalValue: number;
    };
    responseTime: {
      buckets: { dow: number; avgMinutes: number | null; samples: number }[];
      thisWeekAvg: number | null;
      lastWeekAvg: number | null;
    };
    activity: {
      id: string;
      kind: 'message' | 'deal' | 'broadcast' | 'automation' | 'contact';
      text: string;
      at: string;
      href?: string;
    }[];
  };

  const { metrics, conversationsSeries, pipeline, responseTime, activity } =
    props;

  const [range, setRange] = useState<RangeDays>(30);
  const series = useMemo(
    (): Record<RangeDays, ConversationsSeriesPoint[] | null> =>
      conversationsSeries,
    [conversationsSeries],
  );

  const handleRangeChange = useCallback((r: RangeDays) => setRange(r), []);

  return (
    <>
      <Head title="Dashboard" />

      <div className="space-y-5">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Resumen de tu actividad reciente.
          </p>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <MetricCard
            title="Conversaciones activas"
            value={metrics.activeConversations.current.toLocaleString()}
            icon={MessageSquare}
            delta={{
              sign:
                metrics.activeConversations.current -
                metrics.activeConversations.previous,
              label: deltaLabel(
                metrics.activeConversations.current -
                  metrics.activeConversations.previous,
                'activas vs. ayer',
              ),
            }}
          />
          <MetricCard
            title="Contactos nuevos hoy"
            value={metrics.newContactsToday.current.toLocaleString()}
            icon={UserPlus}
            delta={{
              sign:
                metrics.newContactsToday.current -
                metrics.newContactsToday.previous,
              label: deltaLabel(
                metrics.newContactsToday.current -
                  metrics.newContactsToday.previous,
                'vs. ayer',
              ),
            }}
          />
          <MetricCard
            title="Valor de negocios abiertos"
            value={formatCurrency(metrics.openDealsValue)}
            icon={DollarSign}
            subtitle={`${metrics.openDealsCount} negocios abiertos`}
          />
          <MetricCard
            title="Mensajes enviados hoy"
            value={metrics.messagesSentToday.current.toLocaleString()}
            icon={Send}
            delta={{
              sign:
                metrics.messagesSentToday.current -
                metrics.messagesSentToday.previous,
              label: deltaLabel(
                metrics.messagesSentToday.current -
                  metrics.messagesSentToday.previous,
                'vs. ayer',
              ),
            }}
          />
        </div>

        <QuickActions />

        <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
          <div className="h-full lg:col-span-3">
            <ConversationsChart
              series={series}
              loading={false}
              range={range}
              onRangeChange={handleRangeChange}
            />
          </div>
          <div className="h-full lg:col-span-2">
            <PipelineDonut data={pipeline} loading={false} currency="COP" />
          </div>
        </div>

        <ResponseTimeChart data={responseTime} loading={false} />

        <ActivityFeed items={activity} loading={false} />
      </div>
    </>
  );
}

function deltaLabel(delta: number, suffix: string): string {
  if (delta === 0) return `Sin cambios ${suffix}`;
  const sign = delta > 0 ? '+' : '';
  return `${sign}${delta.toLocaleString()} ${suffix}`;
}

Dashboard.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
  ],
};
