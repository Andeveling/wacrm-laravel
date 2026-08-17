import { Head, Link } from '@inertiajs/react';
import {
  ChevronDown,
  ChevronRight,
  CircleAlert,
  CircleCheck,
  Clock,
  PauseCircle,
  PlayCircle,
  UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { flows } from '@/routes';
import { show } from '@/routes/flows';
import type { FlowRun, FlowRunEvent } from '../types';

const STATUS_META: Record<
  FlowRun['status'],
  { label: string; classes: string; icon: typeof Clock }
> = {
  active: {
    label: 'Activo',
    classes: 'border-emerald-600/40 bg-emerald-500/10 text-emerald-300',
    icon: PlayCircle,
  },
  completed: {
    label: 'Completado',
    classes: 'border-border bg-muted text-muted-foreground',
    icon: CircleCheck,
  },
  handed_off: {
    label: 'Transferido',
    classes: 'border-amber-600/40 bg-amber-500/10 text-amber-300',
    icon: UserPlus,
  },
  timed_out: {
    label: 'Expiró',
    classes: 'border-border bg-muted/60 text-muted-foreground',
    icon: Clock,
  },
  paused_by_agent: {
    label: 'Pausado',
    classes: 'border-border bg-muted text-muted-foreground',
    icon: PauseCircle,
  },
  failed: {
    label: 'Falló',
    classes: 'border-red-600/40 bg-red-500/10 text-red-300',
    icon: CircleAlert,
  },
};

function RunCard({
  run,
  events,
  expanded,
  onToggle,
}: {
  run: FlowRun;
  events: FlowRunEvent[];
  expanded: boolean;
  onToggle: () => void;
}) {
  const meta = STATUS_META[run.status];
  const StatusIcon = meta.icon;
  const contactLabel =
    run.contact?.name?.trim() || run.contact?.phone || 'Contacto desconocido';

  return (
    <div className="rounded-lg border border-border bg-card">
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center gap-3 px-4 py-3 text-left"
      >
        {expanded ? (
          <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
        ) : (
          <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="truncate text-sm font-medium text-foreground">
              {contactLabel}
            </span>
            <Badge variant="outline" className={cn('gap-1', meta.classes)}>
              <StatusIcon className="h-3 w-3" />
              {meta.label}
            </Badge>
            {run.status === 'active' && run.current_node_key && (
              <code className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                en {run.current_node_key}
              </code>
            )}
          </div>
          <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
            <span>
              Iniciado {new Date(run.started_at).toLocaleString('es-CO')}
            </span>
            {run.reprompt_count > 0 && (
              <span>· {run.reprompt_count} reintentos</span>
            )}
          </div>
        </div>
      </button>
      {expanded && (
        <div className="border-t border-border px-4 py-3">
          <div className="flex flex-col gap-1">
            {events.length === 0 ? (
              <p className="text-xs text-muted-foreground">Sin eventos.</p>
            ) : (
              events.map((ev, ix) => (
                <div
                  key={ix}
                  className="flex items-start gap-2 rounded-md px-2 py-1 text-xs"
                >
                  <span className="w-32 shrink-0 text-[10px] text-muted-foreground">
                    {new Date(ev.created_at).toLocaleTimeString('es-CO')}
                  </span>
                  <span className="w-32 shrink-0 font-mono text-[10px] text-muted-foreground">
                    {ev.event_type}
                  </span>
                  {ev.node_key && (
                    <code className="shrink-0 rounded bg-muted px-1 py-0.5 text-[10px] text-muted-foreground">
                      {ev.node_key}
                    </code>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default function FlowRunsPage({
  flow,
  runs,
  events,
}: {
  flow: { id: string; name: string };
  runs: FlowRun[];
  events: FlowRunEvent[];
}) {
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  function toggle(runId: string) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(runId)) next.delete(runId);
      else next.add(runId);
      return next;
    });
  }

  return (
    <>
      <Head title={`Ejecuciones — ${flow.name}`} />

      <div className="mx-auto max-w-4xl p-6">
        <Link
          href={show(flow.id)}
          className="mb-2 inline-block text-xs text-muted-foreground hover:text-foreground"
        >
          ← {flow.name}
        </Link>
        <h1 className="text-xl font-semibold text-foreground">
          Historial de ejecuciones
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Las últimas ejecuciones de este flujo, de más reciente a más antigua.
        </p>

        {runs.length === 0 ? (
          <div className="mt-6 rounded-lg border border-dashed border-border bg-card/50 px-6 py-12 text-center text-sm text-muted-foreground">
            Este flujo aún no se ha ejecutado.
          </div>
        ) : (
          <div className="mt-6 flex flex-col gap-2">
            {runs.map((run) => (
              <RunCard
                key={run.id}
                run={run}
                events={events.filter((e) => e.flow_run_id === run.id)}
                expanded={expanded.has(run.id)}
                onToggle={() => toggle(run.id)}
              />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

FlowRunsPage.layout = {
  breadcrumbs: [{ title: 'Flujos', href: flows() }, { title: 'Ejecuciones' }],
};
