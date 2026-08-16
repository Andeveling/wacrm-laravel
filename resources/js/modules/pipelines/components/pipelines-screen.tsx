import { Head, router } from '@inertiajs/react';
import { ChevronDown, GitBranch, Plus, Settings } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import updateDeal from '@/actions/App/Domain/Pipelines/Actions/UpdateDeal';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { pipelines as pipelinesRoute } from '@/routes';
import type { Deal, PipelinesPageProps } from '../types';
import { DealForm } from './deal-form';
import { PipelineAnalytics } from './pipeline-analytics';
import { PipelineBoard } from './pipeline-board';
import { PipelineSettings } from './pipeline-settings';

export default function PipelinesPage({
  pipelines,
  contacts,
}: PipelinesPageProps) {
  const [selectedPipelineId, setSelectedPipelineId] = useState(
    pipelines[0]?.id ?? '',
  );
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [dealFormOpen, setDealFormOpen] = useState(false);
  const [editingDeal, setEditingDeal] = useState<Deal | null>(null);
  const [defaultStageId, setDefaultStageId] = useState('');

  const selectedPipeline =
    pipelines.find((pipeline) => pipeline.id === selectedPipelineId) ??
    pipelines[0] ??
    null;
  const stages = selectedPipeline?.stages ?? [];
  const deals = stages.flatMap((stage) => stage.deals);

  function handleDealMoved(dealId: string, newStageId: string) {
    router.patch(
      updateDeal(dealId),
      { stage_id: newStageId },
      {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['pipelines'] }),
        onError: () => toast.error('No se pudo mover el negocio.'),
      },
    );
  }

  function handleAddDeal(stageId?: string) {
    setEditingDeal(null);
    setDefaultStageId(stageId ?? stages[0]?.id ?? '');
    setDealFormOpen(true);
  }

  function handleEditDeal(deal: Deal) {
    setEditingDeal(deal);
    setDefaultStageId(deal.stage_id);
    setDealFormOpen(true);
  }

  return (
    <>
      <Head title="Pipelines" />

      <div className="space-y-6">
        {selectedPipeline ? (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button
                      type="button"
                      className="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground transition-colors hover:bg-muted data-[popup-open]:bg-muted"
                    >
                      <GitBranch className="h-4 w-4 text-primary" />
                      <span className="font-semibold">
                        {selectedPipeline.name}
                      </span>
                      <ChevronDown className="h-4 w-4 text-muted-foreground" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" className="w-64">
                    <DropdownMenuItem className="text-primary">
                      <GitBranch className="mr-2 h-3.5 w-3.5" />
                      {selectedPipeline.name}
                    </DropdownMenuItem>
                    {pipelines.length > 1 && (
                      <>
                        <DropdownMenuSeparator />
                        {pipelines
                          .filter(
                            (pipeline) => pipeline.id !== selectedPipeline.id,
                          )
                          .map((pipeline) => (
                            <DropdownMenuItem
                              key={pipeline.id}
                              onClick={() => setSelectedPipelineId(pipeline.id)}
                            >
                              <GitBranch className="mr-2 h-3.5 w-3.5" />
                              {pipeline.name}
                            </DropdownMenuItem>
                          ))}
                      </>
                    )}
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
                    toast.info(
                      'La gestión de pipelines aún no está disponible.',
                    )
                  }
                >
                  <Plus className="mr-1 h-4 w-4" />
                  Nuevo pipeline
                </Button>
                <Button
                  onClick={() => handleAddDeal()}
                  disabled={stages.length === 0}
                >
                  <Plus className="mr-1 h-4 w-4" />
                  Nuevo negocio
                </Button>
              </div>
            </div>

            <PipelineAnalytics stages={stages} deals={deals} />
            <PipelineBoard
              stages={stages}
              deals={deals}
              onDealMoved={handleDealMoved}
              onAddDeal={handleAddDeal}
              onEditDeal={handleEditDeal}
            />
          </>
        ) : (
          <div className="rounded-xl border border-dashed border-border p-10 text-center">
            <GitBranch className="mx-auto h-8 w-8 text-muted-foreground" />
            <p className="mt-3 text-sm font-medium text-foreground">
              Aún no tienes pipelines.
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
              Crea un pipeline para empezar a organizar tus negocios.
            </p>
          </div>
        )}
      </div>

      <PipelineSettings
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
        pipeline={
          selectedPipeline ?? {
            id: '',
            name: 'Pipeline',
            created_at: null,
            stages: [],
          }
        }
      />

      <DealForm
        open={dealFormOpen}
        onOpenChange={setDealFormOpen}
        deal={editingDeal}
        pipelineId={selectedPipeline?.id ?? ''}
        stages={stages}
        contacts={contacts}
        defaultStageId={defaultStageId}
      />
    </>
  );
}

PipelinesPage.layout = {
  breadcrumbs: [
    {
      title: 'Pipelines',
      href: pipelinesRoute(),
    },
  ],
};
