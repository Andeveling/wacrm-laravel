import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import type { ApiKey } from '../types';

export function RevokeApiKeyDialog({
  apiKey,
  busy,
  onCancel,
  onConfirm,
}: {
  apiKey: ApiKey | null;
  busy: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  return (
    <Dialog open={!!apiKey} onOpenChange={(open) => !open && onCancel()}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Revocar llave</DialogTitle>
          <DialogDescription>
            {apiKey
              ? `¿Revocar «${apiKey.name}»? Cualquier integración que la use dejará de funcionar de inmediato.`
              : null}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button type="button" variant="ghost" onClick={onCancel}>
            Cancelar
          </Button>
          <Button
            type="button"
            variant="destructive"
            onClick={onConfirm}
            disabled={busy}
          >
            Revocar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
