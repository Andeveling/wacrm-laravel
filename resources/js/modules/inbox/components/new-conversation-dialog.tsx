import { useForm } from '@inertiajs/react';
import { useEffect, useId } from 'react';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/inbox/conversations';
import type { InboxConnection, InboxContact } from '../types';

interface NewConversationDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contacts: InboxContact[];
  connections: InboxConnection[];
}

export function NewConversationDialog({
  open,
  onOpenChange,
  contacts,
  connections,
}: NewConversationDialogProps) {
  const id = useId();
  const form = useForm({ contact_id: '', connection_id: '' });
  const defaultConnection = connections.find(
    (connection) => connection.is_default,
  );

  useEffect(() => {
    if (open) {
      form.setData({
        contact_id: '',
        connection_id: defaultConnection?.id ?? '',
      });
      form.clearErrors();
    }
  }, [defaultConnection?.id, form, open]);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    form.post(store.url(), {
      preserveScroll: true,
      preserveState: false,
      onSuccess: () => onOpenChange(false),
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Nueva conversación</DialogTitle>
          <DialogDescription>
            Elige el contacto y el número de WhatsApp que iniciará el hilo.
          </DialogDescription>
        </DialogHeader>
        <form className="space-y-4" onSubmit={submit}>
          <div className="grid gap-2">
            <Label htmlFor={`${id}-contact`}>Contacto</Label>
            <Select
              value={form.data.contact_id || undefined}
              onValueChange={(contactId) =>
                form.setData('contact_id', contactId)
              }
              disabled={contacts.length === 0}
            >
              <SelectTrigger
                id={`${id}-contact`}
                className="w-full"
                aria-invalid={!!form.errors.contact_id}
              >
                <SelectValue placeholder="Selecciona un contacto" />
              </SelectTrigger>
              <SelectContent>
                {contacts.map((contact) => (
                  <SelectItem key={contact.id} value={contact.id}>
                    {contact.name || contact.phone} — {contact.phone}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {!!form.errors.contact_id && (
              <p className="text-sm text-destructive">
                {form.errors.contact_id}
              </p>
            )}
          </div>
          <div className="grid gap-2">
            <Label htmlFor={`${id}-connection`}>Conexión WhatsApp</Label>
            <Select
              value={form.data.connection_id || undefined}
              onValueChange={(connectionId) =>
                form.setData('connection_id', connectionId)
              }
              disabled={connections.length === 0}
            >
              <SelectTrigger
                id={`${id}-connection`}
                className="w-full"
                aria-invalid={!!form.errors.connection_id}
              >
                <SelectValue
                  placeholder={
                    defaultConnection
                      ? 'Usar conexión predeterminada'
                      : 'Selecciona una conexión'
                  }
                />
              </SelectTrigger>
              <SelectContent>
                {connections.map((connection) => (
                  <SelectItem key={connection.id} value={connection.id}>
                    {connection.phone_number_id}
                    {connection.is_default ? ' (predeterminada)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {!!form.errors.connection_id && (
              <p className="text-sm text-destructive">
                {form.errors.connection_id}
              </p>
            )}
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              disabled={
                form.processing ||
                contacts.length === 0 ||
                connections.length === 0
              }
            >
              {form.processing ? 'Creando…' : 'Crear conversación'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
