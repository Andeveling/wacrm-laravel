import { BarChart3, Bot, PencilLine } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  type ChartConfig,
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatCompactNumber } from '@/lib/currency';

const WINDOWS = [7, 30, 90] as const;

const CHART_CONFIG = {
  tokens: {
    label: 'Tokens',
    color: 'var(--chart-1)',
  },
} satisfies ChartConfig;

function mockUsage(windowDays: number) {
  const daily = Array.from({ length: windowDays }, (_, i) => {
    const d = new Date(Date.now() - (windowDays - i - 1) * 86_400_000);
    const tokens = Math.round(800 + 600 * Math.sin(i / 3) + (i % 5) * 120);
    return {
      date: d.toISOString().slice(0, 10),
      tokens,
      calls: Math.max(1, Math.round(tokens / 250)),
    };
  });
  const totalTokens = daily.reduce((s, d) => s + d.tokens, 0);
  const totalCalls = daily.reduce((s, d) => s + d.calls, 0);
  return {
    totals: { calls: totalCalls, total_tokens: totalTokens },
    by_mode: {
      auto_reply: { tokens: Math.round(totalTokens * 0.65) },
      draft: { tokens: Math.round(totalTokens * 0.35) },
    },
    by_model: [
      {
        model: 'gpt-4o-mini',
        provider: 'openai',
        calls: Math.round(totalCalls * 0.7),
        tokens: Math.round(totalTokens * 0.7),
      },
      {
        model: 'claude-haiku-4.5',
        provider: 'anthropic',
        calls: Math.round(totalCalls * 0.3),
        tokens: Math.round(totalTokens * 0.3),
      },
    ],
    daily,
  };
}

/** Token-spend dashboard for the account's BYO key. */
export function AiUsageCard() {
  const [days, setDays] = useState<number>(30);
  const data = useMemo(() => mockUsage(days), [days]);

  const chartData = data.daily.map((d) => ({
    day: new Date(d.date).toLocaleDateString('es-CO', {
      month: 'short',
      day: 'numeric',
    }),
    tokens: d.tokens,
  }));

  return (
    <Card>
      <CardHeader>
        <div className="flex items-start justify-between gap-4">
          <div>
            <CardTitle className="flex items-center gap-2 text-base">
              <BarChart3 className="size-4 text-primary" /> Consumo de tokens
            </CardTitle>
            <CardDescription>
              Tokens consumidos en tu llave de proveedor por borradores y el bot
              de respuesta automática.
            </CardDescription>
          </div>
          <Select
            value={String(days)}
            onValueChange={(v) => setDays(Number(v))}
          >
            <SelectTrigger className="w-32 shrink-0">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {WINDOWS.map((w) => (
                <SelectItem key={w} value={String(w)}>
                  Últimos {w} días
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Stat
            label="Tokens totales"
            value={formatCompactNumber(data.totals.total_tokens)}
          />
          <Stat label="Llamadas LLM" value={String(data.totals.calls)} />
          <Stat
            label="Auto-respuesta"
            value={formatCompactNumber(data.by_mode.auto_reply.tokens)}
            icon={Bot}
          />
          <Stat
            label="Borradores"
            value={formatCompactNumber(data.by_mode.draft.tokens)}
            icon={PencilLine}
          />
        </div>

        <div>
          <p className="mb-2 text-xs font-medium text-muted-foreground">
            Tokens por día
          </p>
          <TokenUsageBars data={chartData} />
        </div>

        <div>
          <p className="mb-2 text-xs font-medium text-muted-foreground">
            Por modelo
          </p>
          <ul className="divide-y divide-border rounded-md border border-border">
            {data.by_model.map((m) => (
              <li
                key={`${m.provider}:${m.model}`}
                className="flex items-center justify-between px-3 py-2 text-sm"
              >
                <span className="min-w-0 truncate">
                  <span className="text-foreground">{m.model}</span>{' '}
                  <span className="text-xs text-muted-foreground">
                    ({m.provider})
                  </span>
                </span>
                <span className="shrink-0 tabular-nums text-muted-foreground">
                  {formatCompactNumber(m.tokens)} tok · {m.calls}{' '}
                  {m.calls === 1 ? 'llamada' : 'llamadas'}
                </span>
              </li>
            ))}
          </ul>
        </div>
      </CardContent>
    </Card>
  );
}

function TokenUsageBars({
  data,
}: {
  data: Array<{ day: string; tokens: number }>;
}) {
  return (
    <ChartContainer
      config={CHART_CONFIG}
      className="h-[200px] w-full [&_.recharts-cartesian-axis-tick-value]:fill-muted-foreground"
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
          tickFormatter={formatCompactNumber}
        />
        <ChartTooltip cursor={false} content={<ChartTooltipContent />} />
        <Bar dataKey="tokens" fill="var(--color-tokens)" radius={4} />
      </BarChart>
    </ChartContainer>
  );
}

function Stat({
  label,
  value,
  icon: Icon,
}: {
  label: string;
  value: string;
  icon?: typeof Bot;
}) {
  return (
    <div className="rounded-md border border-border p-3">
      <p className="flex items-center gap-1 text-xs text-muted-foreground">
        {Icon ? <Icon className="size-3" /> : null}
        {label}
      </p>
      <p className="mt-1 text-lg font-semibold tabular-nums text-foreground">
        {value}
      </p>
    </div>
  );
}
