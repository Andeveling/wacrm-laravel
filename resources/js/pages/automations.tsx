import { Head, Link, router } from '@inertiajs/react';
import {
  Clock,
  Copy,
  FileText,
  MessageCircle,
  MoreVertical,
  Pencil,
  PhoneCall,
  Plus,
  Trash2,
  Users,
  Zap,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { AUTOMATION_TEMPLATES, mockAutomations } from '@/lib/automations/mock';
import { formatRelative, triggerMeta } from '@/lib/automations/trigger-meta';
import { cn } from '@/lib/utils';
import type { Automation } from '@/types';

const TEMPLATE_ICON: Record<string, typeof Zap> = {
  welcome_message: MessageCircle,
  out_of_office: Clock,
  lead_qualifier: Users,
  follow_up_reminder: PhoneCall,
};

export default function AutomationsPage() {
  const [automations, setAutomations] = useState<Automation[]>(() =>
    mockAutomations(),
  );
  const [pendingDelete, setPendingDelete] = useState<Automation | null>(null);

  function toggleActive(a: Automation, next: boolean) {
    setAutomations((prev) =>
      prev.map((x) => (x.id === a.id ? { ...x, is_active: next } : x)),
    );
    toast.success(
      next ? 'Automatización activada.' : 'Automatización pausada.',
    );
  }

  function duplicate(a: Automation) {
    setAutomations((prev) => [
      {
        ...a,
        id: `${a.id}-copy-${Date.now()}`,
        name: `${a.name} (copia)`,
        execution_count: 0,
        last_executed_at: null,
      },
      ...prev,
    ]);
    toast.success('Automatización duplicada.');
  }

  function confirmDelete() {
    if (!pendingDelete) return;
    setAutomations((prev) => prev.filter((x) => x.id !== pendingDelete.id));
    toast.success('Automatización eliminada.');
    setPendingDelete(null);
  }

  const showTemplates = automations.length < 3;

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
            <Link href="/automations/new">
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
                      router.visit(`/automations/new?template=${tpl.slug}`)
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

        {automations.length === 0 ? (
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
            {automations.map((a) => (
              <AutomationCard
                key={a.id}
                automation={a}
                onToggle={(next) => toggleActive(a, next)}
                onDuplicate={() => duplicate(a)}
                onDelete={() => setPendingDelete(a)}
              />
            ))}
          </ul>
        )}
      </div>

      <Dialog
        open={!!pendingDelete}
        onOpenChange={(v) => !v && setPendingDelete(null)}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Eliminar automatización</DialogTitle>
            <DialogDescription>
              ¿Eliminar «{pendingDelete?.name}»? Esta acción no se puede
              deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setPendingDelete(null)}>
              Cancelar
            </Button>
            <Button variant="destructive" onClick={confirmDelete}>
              <Trash2 className="h-4 w-4" />
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

AutomationsPage.layout = {
  breadcrumbs: [{ title: 'Automatizaciones', href: '/automations' }],
};

function AutomationCard({
  automation,
  onToggle,
  onDuplicate,
  onDelete,
}: {
  automation: Automation;
  onToggle: (next: boolean) => void;
  onDuplicate: () => void;
  onDelete: () => void;
}) {
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

        <Link
          href={`/automations/${automation.id}/edit`}
          className="min-w-0 flex-1 text-left"
        >
          <div className="flex items-center gap-2">
            <span className="truncate text-sm font-semibold text-foreground">
              {automation.name}
            </span>
            {automation.is_active && (
              <span className="relative flex h-2 w-2" aria-label="activa">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-primary" />
              </span>
            )}
          </div>
          {automation.description && (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              {automation.description}
            </p>
          )}
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
            <span>Última: {formatRelative(automation.last_executed_at)}</span>
          </div>
        </Link>

        <div className="flex items-center gap-3">
          <Switch
            checked={automation.is_active}
            onCheckedChange={(v) => onToggle(!!v)}
            aria-label={automation.is_active ? 'Desactivar' : 'Activar'}
          />

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                aria-label="Abrir menú"
                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
              >
                <MoreVertical className="h-4 w-4" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem asChild>
                <Link href={`/automations/${automation.id}/edit`}>
                  <Pencil className="h-4 w-4" />
                  Editar
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onDuplicate}>
                <Copy className="h-4 w-4" />
                Duplicar
              </DropdownMenuItem>
              <DropdownMenuItem asChild>
                <Link href={`/automations/${automation.id}/logs`}>
                  <FileText className="h-4 w-4" />
                  Ver registros
                </Link>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive" onClick={onDelete}>
                <Trash2 className="h-4 w-4" />
                Eliminar
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </li>
  );
}
