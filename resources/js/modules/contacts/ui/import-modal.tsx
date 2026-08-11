import { useForm } from '@inertiajs/react';
import { FileUp } from 'lucide-react';
import { useId } from 'react';
import { toast } from 'sonner';
import importMethod from '@/actions/App/Domain/Contacts/Actions/ImportContacts';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

interface ImportModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onImported: () => void;
}

export function ImportModal({
  open,
  onOpenChange,
  onImported,
}: ImportModalProps) {
  const fileId = useId();
  const form = useForm<{ file: File | null }>({ file: null });

  function submit(event: React.FormEvent) {
    event.preventDefault();

    if (!form.data.file) {
      toast.error('Selecciona un archivo CSV.');
      return;
    }

    form.submit(importMethod(), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Contactos importados.');
        onOpenChange(false);
        onImported();
      },
      onError: () => toast.error('No se pudo importar el archivo.'),
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Importar contactos</DialogTitle>
          <DialogDescription>
            Usa un CSV con las columnas phone, name, email, company y tags.
            Separa varias etiquetas con punto y coma.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor={fileId}>Archivo CSV</Label>
            <input
              id={fileId}
              type="file"
              accept=".csv,.txt,text/csv,text/plain"
              onChange={(event) =>
                form.setData('file', event.target.files?.[0] ?? null)
              }
              className="block w-full rounded-md border px-3 py-2 text-sm"
            />
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing}>
              <FileUp className="size-4" />
              {form.processing ? 'Importando…' : 'Importar'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
