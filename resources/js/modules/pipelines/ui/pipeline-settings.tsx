import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import type { Pipeline } from '../contracts';

interface PipelineSettingsProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pipeline: Pipeline;
}

export function PipelineSettings({
  open,
  onOpenChange,
  pipeline,
}: PipelineSettingsProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Configurar «{pipeline.name}»</DialogTitle>
          <DialogDescription>
            La gestión de etapas y pipelines aún no está disponible.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cerrar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
