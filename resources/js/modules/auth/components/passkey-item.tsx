import { KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import type { Passkey } from '../types';

type Props = {
  passkey: Passkey;
  onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
  const [isDeleting, setIsDeleting] = useState(false);

  const handleDelete = () => {
    setIsDeleting(true);
    onDelete(passkey.id, () => setIsDeleting(false));
  };

  return (
    <div className="flex items-center justify-between border-b p-4 last:border-b-0">
      <div className="flex items-center gap-4">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
          <KeyRound className="h-5 w-5 text-muted-foreground" />
        </div>
        <div className="space-y-1">
          <div className="flex items-center gap-2.5">
            <p className="font-medium tracking-tight">{passkey.name}</p>
            {passkey.authenticator ? (
              <span className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border ring-inset">
                {passkey.authenticator}
              </span>
            ) : null}
          </div>
          <p className="text-sm text-muted-foreground">
            Agregada {passkey.created_at_diff}
            {passkey.last_used_at_diff ? (
              <>
                <span className="mx-1 text-muted-foreground/50">/</span>
                Último uso {passkey.last_used_at_diff}
              </>
            ) : null}
          </p>
        </div>
      </div>

      <Dialog>
        <DialogTrigger
          render={
            <Button
              variant="ghost"
              size="sm"
              className="text-destructive hover:bg-destructive/10 hover:text-destructive"
            />
          }
        >
          <Trash2 className="h-4 w-4" />
          <span className="sr-only">Eliminar</span>
        </DialogTrigger>
        <DialogContent>
          <DialogTitle>Eliminar llave de acceso</DialogTitle>
          <DialogDescription>
            ¿Estás seguro de que quieres eliminar la llave de acceso "
            {passkey.name}"? Ya no podrás usarla para iniciar sesión.
          </DialogDescription>
          <DialogFooter className="gap-2">
            <DialogClose render={<Button variant="secondary" />}>
              Cancelar
            </DialogClose>
            <Button
              variant="destructive"
              onClick={handleDelete}
              disabled={isDeleting}
            >
              {isDeleting ? 'Eliminando…' : 'Eliminar llave de acceso'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
