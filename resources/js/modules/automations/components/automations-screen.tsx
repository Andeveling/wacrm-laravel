import { Head, Link, router } from '@inertiajs/react';
import {
  Clock,
  FileText,
  MessageCircle,
  MoreVertical,
  Pencil,
  PhoneCall,
  Plus,
  Users,
  Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { automations } from '@/routes';
import { edit, logs, newMethod } from '@/routes/automations';
import { AUTOMATION_TEMPLATES } from '../constants/templates';
import { formatRelative, triggerMeta } from '../constants/trigger-meta';
import type { Automation } from '../types';

const TEMPLATE_ICON: Record<string, typeof Zap> = {
  welcome_message: MessageCircle,
  out_of_office: Clock,
  lead_qualifier: Users,
  follow_up_reminder: PhoneCall,
};

export default function AutomationsPage({
  automations: initialAutomations,
}: {
  automations: Automation[];
}) {
  const showTemplates = initialAutomations.length < 3;

  return (
    <>
      <Head title="Automatizaciones" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              Automatizaciones
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Reglas que responden automáticamente a eventos de conversación.
            </p>
          </div>
          <Button asChild>
            <Link href={newMethod()}>
              <Plus className="h-4 w-4" />
              Crear
            </Link>
          </Button>
        </div>

        {showTemplates && (
          <section>
            <h2 className="mb-3 text-sm font-semibold text-muted-foreground">
              Empieza con una plantilla
            </h2>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
              {AUTOMATION_TEMPLATES.map((tpl) => {
                const Icon = TEMPLATE_ICON[tpl.slug] ?? Zap;
                return (
                  <button
                    key={tpl.slug}
                    type="button"
                    onClick={() =>
                      router.visit(newMethod({ query: { template: tpl.slug } }))
                    }
                    className="group flex flex-col items-start rounded-xl border border-border bg-card p-4 text-left transition-colors hover:border-primary/50 hover:bg-card/80"
                  >
                    <div className="mb-3 flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary/15">
                      <Icon className="h-5 w-5" />
                    </div>
                    <div className="text-sm font-semibold text-foreground">
                      {tpl.name}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                      {tpl.description}
                    </p>
                  </button>
                );
              })}
            </div>
          </section>
        )}

        {initialAutomations.length === 0 ? (
          <div className="flex h-48 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/40">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
              <Zap className="h-6 w-6 text-primary" />
            </div>
            <p className="mt-3 text-sm font-medium text-foreground">
              Sin automatizaciones todavía
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Crea tu primera automatización para empezar.
            </p>
          </div>
        ) : (
          <ul className="space-y-3">
            {initialAutomations.map((a) => (
              <AutomationCard key={a.id} automation={a} />
            ))}
          </ul>
        )}
      </div>
    </>
  );
}

AutomationsPage.layout = {
  breadcrumbs: [{ title: 'Automatizaciones', href: automations() }],
};

function AutomationCard({ automation }: { automation: Automation }) {
  const meta = triggerMeta(automation.trigger_type);
  return (
    <li className="rounded-xl border border-border bg-card transition-colors hover:border-border">
      <div className="flex items-center gap-4 p-4">
        <div
          className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10"
          aria-hidden
        >
          <Zap className="h-5 w-5 text-primary" />
        </div>

        <Link href={edit(automation.id)} className="min-w-0 flex-1 text-left">
          <div className="flex items-center gap-2">
            <span className="truncate text-sm font-semibold text-foreground">
              {automation.name}
            </span>
            <span
              className={cn(
                'rounded-full border px-2 py-0.5 text-[11px] font-medium',
                automation.is_active
                  ? 'border-primary/30 bg-primary/10 text-primary'
                  : 'border-border bg-muted text-muted-foreground',
              )}
            >
              {automation.is_active ? 'Activa' : 'Pausada'}
            </span>
          </div>
          {automation.description ? (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              {automation.description}
            </p>
          ) : null}
          <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span
              className={cn(
                'inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium',
                meta.pillClass,
              )}
            >
              {meta.label}
            </span>
            <span className="tabular-nums">
              {automation.execution_count} ejecuciones
            </span>
            <span aria-hidden>·</span>
            <span className="tabular-nums">
              {automation.steps.length} pasos
            </span>
            <span aria-hidden>·</span>
            <span className="truncate">
              {automation.steps.map((step) => step.step_type).join(', ')}
            </span>
            <span aria-hidden>·</span>
            <span>Última: {formatRelative(automation.last_executed_at)}</span>
          </div>
        </Link>

        <div className="flex items-center">
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <button
                  type="button"
                  aria-label="Abrir menú"
                  className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                />
              }
            >
              <MoreVertical className="h-4 w-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem render={<Link href={edit(automation.id)} />}>
                <Pencil className="h-4 w-4" />
                Editar
              </DropdownMenuItem>
              <DropdownMenuItem render={<Link href={logs(automation.id)} />}>
                <FileText className="h-4 w-4" />
                Ver registros
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </li>
  );
}
