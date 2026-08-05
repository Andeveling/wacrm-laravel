import { Head, router } from '@inertiajs/react';
import {
  Archive,
  FileText,
  HelpCircle,
  MessageSquare,
  Pencil,
  PlayCircle,
  Plus,
  Trash2,
  UserPlus,
  Workflow,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { FLOW_TEMPLATES, mockFlows } from '@/lib/flows/mock';
import { cn } from '@/lib/utils';
import type { Flow } from '@/types';

const STATUS_LABELS: Record<Flow['status'], string> = {
  draft: 'Borrador',
  active: 'Activo',
  archived: 'Archivado',
};
const STATUS_COLORS: Record<Flow['status'], string> = {
  draft: 'border-border bg-muted text-muted-foreground',
  active: 'border-emerald-600/40 bg-emerald-500/10 text-emerald-300',
  archived: 'border-border bg-muted/50 text-muted-foreground',
};

const TEMPLATE_ICONS: Record<string, typeof Workflow> = {
  faq_bot: HelpCircle,
  lead_capture: UserPlus,
  appointment: MessageSquare,
};

function describeTrigger(flow: Flow): string {
  if (flow.trigger_type === 'keyword') {
    const keywords = Array.isArray(
      (flow.trigger_config as { keywords?: string[] }).keywords,
    )
      ? ((flow.trigger_config as { keywords?: string[] }).keywords ?? [])
      : [];
    return keywords.length === 0
      ? 'Sin palabras clave'
      : `Palabras clave: ${keywords.join(', ')}`;
  }
  if (flow.trigger_type === 'first_inbound_message')
    return 'Primer mensaje del contacto';
  return 'Disparo manual';
}

export default function FlowsPage() {
  const [flows, setFlows] = useState<Flow[]>(() => mockFlows());
  const [createOpen, setCreateOpen] = useState(false);
  const [newName, setNewName] = useState('');

  function handleCreate() {
    if (!newName.trim()) return;
    const flow: Flow = {
      id: `flow-${Date.now()}`,
      name: newName.trim(),
      description: null,
      status: 'draft',
      trigger_type: 'keyword',
      trigger_config: { keywords: [] },
      execution_count: 0,
      last_executed_at: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    setCreateOpen(false);
    setNewName('');
    router.visit(`/flows/${flow.id}`);
  }

  function handleUseTemplate(slug: string) {
    setCreateOpen(false);
    router.visit(`/flows/tpl-${slug}`);
  }

  function handleDelete(flow: Flow) {
    setFlows((prev) => prev.filter((f) => f.id !== flow.id));
    toast.success('Flujo eliminado.');
  }

  return (
    <>
      <Head title="Flujos" />

      <div className="space-y-6 p-6">
        <header className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-semibold text-foreground">Flujos</h1>
              <span className="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-300 uppercase">
                Beta
              </span>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Conversaciones automatizadas de varios pasos activadas por palabra
              clave o mensaje entrante.
            </p>
          </div>
          <Button onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4" />
            Nuevo flujo
          </Button>
        </header>

        {flows.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card/50 px-6 py-16 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
              <Workflow className="h-6 w-6 text-muted-foreground" />
            </div>
            <h2 className="mt-4 text-base font-medium text-foreground">
              Sin flujos todavía
            </h2>
            <p className="mt-1 max-w-md text-sm text-muted-foreground">
              Crea tu primer flujo para automatizar conversaciones de varios
              pasos.
            </p>
            <Button className="mt-5" onClick={() => setCreateOpen(true)}>
              <Plus className="h-4 w-4" />
              Crear el primero
            </Button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {flows.map((flow) => (
              <FlowCard
                key={flow.id}
                flow={flow}
                onEdit={() => router.visit(`/flows/${flow.id}`)}
                onDelete={() => handleDelete(flow)}
              />
            ))}
          </div>
        )}

        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
          <DialogContent className="sm:max-w-4xl">
            <DialogHeader>
              <DialogTitle>Crear flujo</DialogTitle>
              <DialogDescription>
                Empieza desde una plantilla o crea un flujo en blanco.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3">
              <p className="text-xs tracking-wide text-muted-foreground uppercase">
                Plantillas
              </p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {FLOW_TEMPLATES.map((template) => {
                  const Icon = TEMPLATE_ICONS[template.slug] ?? FileText;
                  return (
                    <button
                      key={template.slug}
                      type="button"
                      onClick={() => handleUseTemplate(template.slug)}
                      className="flex flex-col gap-2.5 rounded-lg border border-border bg-background p-4 text-left transition-colors hover:border-primary/40 hover:bg-muted"
                    >
                      <Icon className="h-5 w-5 text-primary" />
                      <span className="text-sm font-semibold text-foreground">
                        {template.name}
                      </span>
                      <span className="text-xs leading-relaxed text-muted-foreground">
                        {template.description}
                      </span>
                      <span className="mt-auto border-t border-border pt-2 text-[11px] text-muted-foreground">
                        {template.nodeCount} nodos
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="space-y-2 border-t border-border pt-4">
              <p className="text-xs tracking-wide text-muted-foreground uppercase">
                Empezar en blanco
              </p>
              <Input
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                placeholder="Nombre del flujo"
                onKeyDown={(e) => {
                  if (e.key === 'Enter') handleCreate();
                }}
              />
            </div>

            <DialogFooter>
              <Button variant="ghost" onClick={() => setCreateOpen(false)}>
                Cancelar
              </Button>
              <Button onClick={handleCreate} disabled={!newName.trim()}>
                Crear en blanco
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
}

FlowsPage.layout = {
  breadcrumbs: [{ title: 'Flujos', href: '/flows' }],
};

function FlowCard({
  flow,
  onEdit,
  onDelete,
}: {
  flow: Flow;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const triggerSummary = describeTrigger(flow);
  const StatusIcon =
    flow.status === 'active'
      ? PlayCircle
      : flow.status === 'archived'
        ? Archive
        : PlayCircle;
  return (
    <div className="flex flex-col rounded-lg border border-border bg-card p-4 transition-colors hover:border-border">
      <div className="flex items-start justify-between gap-2">
        <div className="flex min-w-0 items-center gap-2">
          <Workflow className="h-4 w-4 shrink-0 text-primary" />
          <h3 className="truncate text-sm font-semibold text-foreground">
            {flow.name}
          </h3>
        </div>
        <Badge
          variant="outline"
          className={cn(
            'shrink-0 gap-1 text-[10px]',
            STATUS_COLORS[flow.status],
          )}
        >
          <StatusIcon className="h-3 w-3" />
          {STATUS_LABELS[flow.status]}
        </Badge>
      </div>

      <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">
        {flow.description || triggerSummary}
      </p>

      <div className="mt-4 flex items-center gap-3 text-[11px] text-muted-foreground">
        <span className="inline-flex items-center gap-1">
          <MessageSquare className="h-3 w-3" />
          {flow.execution_count} ejecuciones
        </span>
      </div>

      <div className="mt-4 flex items-center justify-end gap-2 border-t border-border pt-3">
        <Button variant="ghost" size="sm" onClick={onEdit}>
          <Pencil className="h-3.5 w-3.5" />
          Editar
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={onDelete}
          className="text-red-400 hover:bg-red-500/10 hover:text-red-300"
        >
          <Trash2 className="h-3.5 w-3.5" />
          Eliminar
        </Button>
      </div>
    </div>
  );
}
