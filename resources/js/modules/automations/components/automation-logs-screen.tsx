import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { automations } from '@/routes';
import { formatRelative } from '../constants/trigger-meta';
import type { AutomationLog, AutomationLogStepResult } from '../types';

const STATUS_LABEL: Record<AutomationLog['status'], string> = {
  success: 'Éxito',
  partial: 'Parcial',
  failed: 'Fallido',
};

function StatusBadge({ status }: { status: AutomationLog['status'] }) {
  const classes =
    status === 'success'
      ? 'border-primary/30 bg-primary/10 text-primary'
      : status === 'partial'
        ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
        : 'border-red-500/30 bg-red-500/10 text-red-300';
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium',
        classes,
      )}
    >
      {STATUS_LABEL[status]}
    </span>
  );
}

function StepRow({ result }: { result: AutomationLogStepResult }) {
  const ok = result.status === 'success';
  return (
    <li className="flex items-start gap-2 text-xs">
      <span
        className={cn(
          'mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full',
          ok ? 'bg-primary/20 text-primary' : 'bg-red-500/20 text-red-400',
        )}
        aria-hidden
      >
        {ok ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}
      </span>
      <span className="text-muted-foreground">{result.step_type}</span>
      {result.detail && (
        <span className="truncate text-muted-foreground">
          — {result.detail}
        </span>
      )}
    </li>
  );
}

export default function AutomationLogsPage({
  automation,
  logs,
}: {
  automation: { id: string; name: string };
  logs: AutomationLog[];
}) {
  const [openLogId, setOpenLogId] = useState<string | null>(null);

  return (
    <>
      <Head title={`Registros — ${automation.name}`} />

      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <Link
            href={automations()}
            className="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            aria-label="Volver"
          >
            <ArrowLeft className="h-4 w-4" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              {automation.name}
            </h1>
            <p className="mt-0.5 text-sm text-muted-foreground">
              Registros de ejecución
            </p>
          </div>
        </div>

        {logs.length === 0 ? (
          <div className="flex h-48 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40">
            <p className="text-sm text-foreground">Sin ejecuciones todavía</p>
            <p className="mt-1 text-xs text-muted-foreground">
              Las ejecuciones de esta automatización aparecerán aquí.
            </p>
          </div>
        ) : (
          <ul className="space-y-2">
            {logs.map((log) => {
              const isOpen = openLogId === log.id;
              return (
                <li
                  key={log.id}
                  className="rounded-xl border border-border bg-card"
                >
                  <button
                    type="button"
                    onClick={() => setOpenLogId(isOpen ? null : log.id)}
                    className="flex w-full items-center gap-3 px-4 py-3 text-left"
                  >
                    {isOpen ? (
                      <ChevronDown className="h-4 w-4 text-muted-foreground" />
                    ) : (
                      <ChevronRight className="h-4 w-4 text-muted-foreground" />
                    )}
                    <StatusBadge status={log.status} />
                    <div className="min-w-0 flex-1">
                      <div className="truncate text-sm font-medium text-foreground">
                        {log.contact?.name ??
                          log.contact?.phone ??
                          'Contacto desconocido'}
                      </div>
                      <div className="truncate text-xs text-muted-foreground">
                        {log.trigger_event} · {log.steps_executed.length} pasos
                      </div>
                    </div>
                    <div className="text-xs text-muted-foreground">
                      {formatRelative(log.created_at)}
                    </div>
                  </button>
                  {isOpen && (
                    <div className="border-t border-border px-4 py-3">
                      {log.error_message && (
                        <p className="mb-3 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-300">
                          {log.error_message}
                        </p>
                      )}
                      <ul className="space-y-1.5">
                        {log.steps_executed.length === 0 ? (
                          <li className="text-xs text-muted-foreground">
                            Sin pasos ejecutados.
                          </li>
                        ) : (
                          log.steps_executed.map((r) => (
                            <StepRow key={r.step_id} result={r} />
                          ))
                        )}
                      </ul>
                    </div>
                  )}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </>
  );
}

AutomationLogsPage.layout = {
  breadcrumbs: [
    { title: 'Automatizaciones', href: automations() },
    { title: 'Registros' },
  ],
};
