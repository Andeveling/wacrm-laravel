import { Head, Link, router } from '@inertiajs/react';
import {
  Archive,
  HelpCircle,
  MessageSquare,
  PlayCircle,
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
import { cn } from '@/lib/utils';
import { flows as flowsRoute } from '@/routes';
import { runs, show } from '@/routes/flows';
import type { Flow } from '../contracts';
import { FLOW_TEMPLATES } from '../templates';

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

function describeTrigger(flow: Flow): string {
  if (flow.trigger_type === 'keyword') {
    const config = flow.trigger_config as { keywords?: string[] };
    const keywords = Array.isArray(config.keywords) ? config.keywords : [];

    return keywords.length === 0
      ? 'Sin palabras clave'
      : `Palabras clave: ${keywords.join(', ')}`;
  }

  return flow.trigger_type === 'first_inbound_message'
    ? 'Primer mensaje del contacto'
    : 'Disparo manual';
}

export default function FlowsPage({ flows }: { flows: Flow[] }) {
  const [visibleFlows, setVisibleFlows] = useState(flows);
  const [createOpen, setCreateOpen] = useState(false);
  const [newName, setNewName] = useState('');

  function handleCreate(): void {
    if (!newName.trim()) return;
    setCreateOpen(false);
    setNewName('');
    router.visit(show(`new-${Date.now()}`));
  }

  function handleDelete(flow: Flow): void {
    setVisibleFlows((current) => current.filter((item) => item.id !== flow.id));
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
              <span className="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-300 uppercase">
                Beta
              </span>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Conversaciones automatizadas de varios pasos activadas por palabra
              clave o mensaje entrante.
            </p>
          </div>
          <div className="flex items-center gap-3">
            <div className="rounded-full border border-border bg-card px-3 py-1 text-xs text-muted-foreground">
              {visibleFlows.length} flujos
            </div>
            <Button onClick={() => setCreateOpen(true)}>Nuevo flujo</Button>
          </div>
        </header>

        <section className="rounded-xl border border-border bg-card p-5">
          <h2 className="text-sm font-semibold text-foreground">Plantillas</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Catálogo fijo de puntos de partida para nuevos flows.
          </p>
          <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {FLOW_TEMPLATES.map((template) => {
              const Icon =
                template.slug === 'lead_capture'
                  ? UserPlus
                  : template.slug === 'appointment'
                    ? MessageSquare
                    : HelpCircle;

              return (
                <div
                  key={template.slug}
                  className="flex flex-col gap-2 rounded-lg border border-border bg-background p-4"
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
                </div>
              );
            })}
          </div>
        </section>

        {visibleFlows.length === 0 ? (
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
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {visibleFlows.map((flow) => (
              <FlowCard
                key={flow.id}
                flow={flow}
                onDelete={() => handleDelete(flow)}
              />
            ))}
          </div>
        )}

        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Crear flujo</DialogTitle>
              <DialogDescription>
                Empieza desde una plantilla o crea un flujo en blanco.
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-3 sm:grid-cols-3">
              {FLOW_TEMPLATES.map((template) => (
                <button
                  key={template.slug}
                  type="button"
                  onClick={() => router.visit(show(`tpl-${template.slug}`))}
                  className="rounded-lg border border-border p-3 text-left hover:bg-muted"
                >
                  <span className="text-sm font-semibold text-foreground">
                    {template.name}
                  </span>
                  <span className="mt-1 block text-xs text-muted-foreground">
                    {template.nodeCount} nodos
                  </span>
                </button>
              ))}
            </div>
            <Input
              value={newName}
              onChange={(event) => setNewName(event.target.value)}
              placeholder="Nombre del flujo"
              onKeyDown={(event) => {
                if (event.key === 'Enter') handleCreate();
              }}
            />
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
  breadcrumbs: [{ title: 'Flujos', href: flowsRoute() }],
};

function FlowCard({ flow, onDelete }: { flow: Flow; onDelete: () => void }) {
  const StatusIcon = flow.status === 'archived' ? Archive : PlayCircle;
  const nodesCount = flow.nodes_count ?? flow.nodes?.length ?? 0;

  return (
    <div className="flex flex-col rounded-lg border border-border bg-card p-4">
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
        {flow.description}
      </p>
      <p className="mt-1 text-xs text-muted-foreground">
        {describeTrigger(flow)}
      </p>
      <div className="mt-4 flex items-center gap-3 text-[11px] text-muted-foreground">
        <span className="inline-flex items-center gap-1">
          <MessageSquare className="h-3 w-3" />
          {flow.execution_count} ejecuciones
        </span>
        <span>{nodesCount} nodos</span>
      </div>
      <div className="mt-4 flex items-center justify-end gap-2 border-t border-border pt-3">
        <Button variant="ghost" size="sm" asChild>
          <Link href={runs(flow.id)}>Ejecuciones</Link>
        </Button>
        <Button variant="outline" size="sm" asChild>
          <Link href={show(flow.id)}>Abrir editor</Link>
        </Button>
        <Button variant="ghost" size="sm" onClick={onDelete}>
          Eliminar
        </Button>
      </div>
    </div>
  );
}
