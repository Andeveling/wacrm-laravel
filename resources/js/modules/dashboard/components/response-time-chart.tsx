import { Clock } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
  type ChartConfig,
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import { DOW_SHORT_MON_FIRST } from '../helpers/date-utils';
import type { ResponseTimeSummary } from '../types';
import { EmptyState } from './empty-state';
import { Skeleton } from './skeleton';

interface ResponseTimeChartProps {
  data: ResponseTimeSummary | null;
  loading: boolean;
  /** Minutes. Surfaced as a "target" pill in the header. */
  thresholdMinutes?: number;
}

const MINUTES_KEY = 'minutes';

const CHART_CONFIG = {
  [MINUTES_KEY]: {
    label: 'Minutos prom.',
    color: 'var(--chart-1)',
  },
} satisfies ChartConfig;

export function ResponseTimeChart({
  data,
  loading,
  thresholdMinutes = 5,
}: ResponseTimeChartProps) {
  const hasData = data?.buckets.some((b) => b.avgMinutes != null) ?? false;

  const chartData =
    data?.buckets.map((b, i) => ({
      day: DOW_SHORT_MON_FIRST[i],
      [MINUTES_KEY]: b.avgMinutes ?? 0,
    })) ?? [];

  return (
    <section className="rounded-xl border border-border bg-card">
      <header className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">
            Tiempo de respuesta
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Promedio de primera respuesta por día
          </p>
        </div>
        <div className="flex items-center gap-3 text-right text-xs">
          {thresholdMinutes > 0 && (
            <span className="rounded-full border border-rose-500/40 bg-rose-500/10 px-2 py-0.5 font-medium text-rose-300 tabular-nums">
              Meta: {thresholdMinutes}m
            </span>
          )}
          {data && (data.thisWeekAvg != null || data.lastWeekAvg != null) && (
            <div>
              <div className="text-muted-foreground">
                Esta semana{' '}
                <span className="font-medium text-foreground tabular-nums">
                  {fmt(data.thisWeekAvg)}
                </span>
              </div>
              <div className="text-muted-foreground">
                Semana pasada{' '}
                <span className="tabular-nums">{fmt(data.lastWeekAvg)}</span>
              </div>
            </div>
          )}
        </div>
      </header>

      <div className="p-5">
        {loading || !data ? (
          <Skeleton className="h-65 w-full" />
        ) : !hasData ? (
          <EmptyState
            icon={Clock}
            title="Sin respuestas"
            hint="No hay datos de tiempo de respuesta todavía."
          />
        ) : (
          <ResponseTimeBars data={chartData} />
        )}
      </div>
    </section>
  );
}

function ResponseTimeBars({
  data,
}: {
  data: Array<{ day: string; minutes: number }>;
}) {
  return (
    <ChartContainer
      config={CHART_CONFIG}
      className="h-65 w-full [&_.recharts-cartesian-axis-tick-value]:fill-muted-foreground"
    >
      <BarChart accessibilityLayer data={data}>
        <CartesianGrid vertical={false} />
        <XAxis
          dataKey="day"
          tickLine={false}
          tickMargin={10}
          axisLine={false}
        />
        <YAxis
          tickLine={false}
          axisLine={false}
          width={48}
          tickFormatter={formatMinutes}
        />
        <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
        <Bar dataKey={MINUTES_KEY} fill="var(--color-minutes)" radius={4} />
      </BarChart>
    </ChartContainer>
  );
}

function formatMinutes(value: number): string {
  return `${value.toFixed(1)}m`;
}

function fmt(mins: number | null): string {
  if (mins == null) return '—';
  if (mins < 1) return `${Math.max(1, Math.round(mins * 60))}s`;
  if (mins < 60) return `${mins.toFixed(1)}m`;
  return `${(mins / 60).toFixed(1)}h`;
}
