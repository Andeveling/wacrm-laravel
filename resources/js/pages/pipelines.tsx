import { Head } from '@inertiajs/react';
import { ChevronDown, GitBranch, Plus, Settings } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DealForm } from '@/components/pipelines/deal-form';
import { PipelineAnalytics } from '@/components/pipelines/pipeline-analytics';
import { PipelineBoard } from '@/components/pipelines/pipeline-board';
import { PipelineSettings } from '@/components/pipelines/pipeline-settings';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MOCK_PIPELINE, MOCK_STAGES, mockDeals } from '@/lib/pipelines/mock';
import type { Deal } from '@/types';

export default function PipelinesPage() {
  const [deals, setDeals] = useState<Deal[]>(() => mockDeals());
  const [settingsOpen, setSettingsOpen] = useState(false);

  const [dealFormOpen, setDealFormOpen] = useState(false);
  const [editingDeal, setEditingDeal] = useState<Deal | null>(null);
  const [defaultStageId, setDefaultStageId] = useState<string>('');

  function handleDealMoved(dealId: string, newStageId: string) {
    setDeals((prev) =>
      prev.map((d) => (d.id === dealId ? { ...d, stage_id: newStageId } : d)),
    );
  }

  function handleAddDeal(stageId?: string) {
    setEditingDeal(null);
    setDefaultStageId(stageId ?? MOCK_STAGES[0]?.id ?? '');
    setDealFormOpen(true);
  }

  function handleEditDeal(deal: Deal) {
    setEditingDeal(deal);
    setDefaultStageId(deal.stage_id);
    setDealFormOpen(true);
  }

  function handleDealSaved(deal: Deal) {
    setDeals((prev) => {
      const exists = prev.some((d) => d.id === deal.id);
      return exists
        ? prev.map((d) => (d.id === deal.id ? deal : d))
        : [deal, ...prev];
    });
  }

  function handleDealDeleted(dealId: string) {
    setDeals((prev) => prev.filter((d) => d.id !== dealId));
  }

  return (
    <>
      <Head title="Pipelines" />

      <div className="space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <button
                  type="button"
                  className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground transition-colors hover:bg-muted data-[popup-open]:bg-muted"
                >
                  <GitBranch className="h-4 w-4 text-primary" />
                  <span className="font-semibold">{MOCK_PIPELINE.name}</span>
                  <ChevronDown className="h-4 w-4 text-muted-foreground" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start" className="w-64">
                <DropdownMenuItem className="text-primary">
                  <GitBranch className="mr-2 h-3.5 w-3.5" />
                  {MOCK_PIPELINE.name}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => setSettingsOpen(true)}>
                  <Settings className="mr-2 h-3.5 w-3.5" />
                  Gestionar pipelines
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() =>
                toast.info('La gestión de pipelines aún no está disponible.')
              }
            >
              <Plus className="mr-1 h-4 w-4" />
              Nuevo pipeline
            </Button>
            <Button onClick={() => handleAddDeal()}>
              <Plus className="mr-1 h-4 w-4" />
              Nuevo negocio
            </Button>
          </div>
        </div>

        <PipelineAnalytics stages={MOCK_STAGES} deals={deals} />
        <PipelineBoard
          stages={MOCK_STAGES}
          deals={deals}
          onDealMoved={handleDealMoved}
          onAddDeal={handleAddDeal}
          onEditDeal={handleEditDeal}
        />
      </div>

      <PipelineSettings
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
        pipeline={MOCK_PIPELINE}
      />

      <DealForm
        open={dealFormOpen}
        onOpenChange={setDealFormOpen}
        deal={editingDeal}
        pipelineId={MOCK_PIPELINE.id}
        stages={MOCK_STAGES}
        defaultStageId={defaultStageId}
        onSaved={handleDealSaved}
        onDeleted={handleDealDeleted}
      />
    </>
  );
}

PipelinesPage.layout = {
  breadcrumbs: [
    {
      title: 'Pipelines',
      href: '/pipelines',
    },
  ],
};
