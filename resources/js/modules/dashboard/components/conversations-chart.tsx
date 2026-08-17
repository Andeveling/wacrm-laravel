import { MessageSquare } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import {
  type ChartConfig,
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';
import type { ConversationsSeriesPoint } from '../types';
import { EmptyState } from './empty-state';
import { Skeleton } from './skeleton';

type RangeDays = 7 | 30 | 90;

interface ConversationsChartProps {
  /** Per-range data, so switching tabs never re-fetches. */
  series: Record<RangeDays, ConversationsSeriesPoint[] | null>;
  loading: boolean;
  range: RangeDays;
  onRangeChange: (r: RangeDays) => void;
}

const CHART_CONFIG = {
  incoming: {
    label: 'Entrantes',
    color: 'var(--chart-1)',
  },
  outgoing: {
    label: 'Salientes',
    color: 'var(--chart-2)',
  },
} satisfies ChartConfig;

export function ConversationsChart({
  series,
  loading,
  range,
  onRangeChange,
}: ConversationsChartProps) {
  const data = series[range];

  return (
    <section className="flex h-full flex-col rounded-xl border border-border bg-card">
      <header className="flex items-center justify-between border-b border-border px-5 py-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">
            Conversaciones
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Mensajes entrantes y salientes por día
          </p>
        </div>
        <div className="flex items-center gap-1 rounded-lg bg-muted/60 p-1">
          {[7, 30, 90].map((r) => (
            <button
              key={r}
              type="button"
              onClick={() => onRangeChange(r as RangeDays)}
              className={cn(
                'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                range === r
                  ? 'bg-secondary text-secondary-foreground'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              {r}d
            </button>
          ))}
        </div>
      </header>

      <div className="p-5">
        {loading || !data ? (
          <Skeleton className="h-[240px] w-full" />
        ) : data.every((p) => p.incoming === 0 && p.outgoing === 0) ? (
          <EmptyState
            icon={MessageSquare}
            title="Sin actividad"
            hint="No hay conversaciones en este rango."
          />
        ) : (
          <ConversationsLines data={data} />
        )}
      </div>
    </section>
  );
}

function ConversationsLines({ data }: { data: ConversationsSeriesPoint[] }) {
  return (
    <ChartContainer
      config={CHART_CONFIG}
      className="h-[240px] w-full [&_.recharts-cartesian-axis-tick-value]:fill-muted-foreground"
    >
      <LineChart accessibilityLayer data={data}>
        <CartesianGrid vertical={false} />
        <XAxis
          dataKey="day"
          tickLine={false}
          axisLine={false}
          tickMargin={8}
          minTickGap={24}
          tickFormatter={shortDayLabel}
        />
        <YAxis
          tickLine={false}
          axisLine={false}
          width={36}
          allowDecimals={false}
        />
        <ChartTooltip
          content={
            <ChartTooltipContent
              labelFormatter={(value) =>
                typeof value === 'string'
                  ? longDayLabel(value)
                  : String(value ?? '')
              }
            />
          }
        />
        <ChartLegend content={<ChartLegendContent />} />
        <Line
          dataKey="incoming"
          type="linear"
          stroke="var(--color-incoming)"
          strokeWidth={2}
          dot={false}
        />
        <Line
          dataKey="outgoing"
          type="linear"
          stroke="var(--color-outgoing)"
          strokeWidth={2}
          dot={false}
        />
      </LineChart>
    </ChartContainer>
  );
}

function shortDayLabel(key: string): string {
  const [y, m, d] = key.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return date.toLocaleDateString('es-CO', { month: 'short', day: 'numeric' });
}

function longDayLabel(key: string): string {
  const [y, m, d] = key.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return date.toLocaleDateString('es-CO', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}
