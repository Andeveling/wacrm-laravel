import { ArrowLeft, CalendarClock, Save, Users } from 'lucide-react';
import { useId, useState } from 'react';
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
import type {
  BroadcastConnection,
  BroadcastTag,
  MessageTemplate,
} from '../types';
import type { AudienceConfig } from './step2-select-audience';

interface Step4Props {
  name: string;
  onNameChange: (name: string) => void;
  template: MessageTemplate;
  audience: AudienceConfig;
  tags: BroadcastTag[];
  audienceCount: number;
  scheduledAt: string;
  onScheduledAtChange: (scheduledAt: string) => void;
  connections: BroadcastConnection[];
  connectionId: string;
  onConnectionChange: (connectionId: string) => void;
  onSend: () => void;
  onBack: () => void;
}

export function Step4ScheduleSend({
  name,
  onNameChange,
  template,
  audience,
  tags,
  audienceCount,
  scheduledAt,
  onScheduledAtChange,
  connections,
  connectionId,
  onConnectionChange,
  onSend,
  onBack,
}: Step4Props) {
  const [showConfirm, setShowConfirm] = useState(false);
  const nameInputId = useId();
  const scheduledAtInputId = useId();
  const connectionInputId = useId();
  const audienceLabel =
    audience.type === 'all'
      ? 'Todos los contactos'
      : (audience.tagIds?.length ?? 0) === 0
        ? 'Todos los contactos'
        : `Por etiqueta (${(audience.tagIds ?? []).map((id) => tags.find((tag) => tag.id === id)?.name).join(', ')})`;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-foreground">Revisa y crea</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Confirma los detalles antes de crear la difusión.
        </p>
      </div>

      <div>
        <label
          htmlFor={nameInputId}
          className="mb-1.5 block text-sm font-medium text-foreground"
        >
          Nombre de la difusión
        </label>
        <Input
          id={nameInputId}
          value={name}
          onChange={(e) => onNameChange(e.target.value)}
          placeholder="Ej. Promo de julio"
        />
      </div>

      <div className="space-y-3 rounded-xl border border-border bg-card/50 p-4">
        <p className="text-sm font-medium text-foreground">Resumen</p>
        <div className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <p className="text-xs text-muted-foreground">Plantilla</p>
            <p className="text-foreground">{template.name}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Audiencia</p>
            <p className="text-foreground">{audienceLabel}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Alcance estimado</p>
            <div className="flex items-center gap-1.5">
              <Users className="h-3.5 w-3.5 text-primary" />
              <p className="font-medium text-foreground">
                {audienceCount.toLocaleString()}
              </p>
            </div>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Idioma</p>
            <p className="text-foreground">{template.language ?? 'es_CO'}</p>
          </div>
        </div>
      </div>

      <div>
        <label
          htmlFor={connectionInputId}
          className="mb-1.5 block text-sm font-medium text-foreground"
        >
          Conexión de envío
        </label>
        <select
          id={connectionInputId}
          value={connectionId}
          onChange={(event) => onConnectionChange(event.target.value)}
          className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
        >
          <option value="">Selecciona una conexión activa</option>
          {connections.map((connection) => (
            <option key={connection.id} value={connection.id}>
              {connection.phone_number_id}
              {connection.is_default ? ' (predeterminada)' : ''}
            </option>
          ))}
        </select>
      </div>

      <div>
        <label
          htmlFor={scheduledAtInputId}
          className="mb-1.5 block text-sm font-medium text-foreground"
        >
          Programar para más tarde{' '}
          <span className="font-normal text-muted-foreground">(opcional)</span>
        </label>
        <Input
          id={scheduledAtInputId}
          type="datetime-local"
          value={scheduledAt}
          onChange={(event) => onScheduledAtChange(event.target.value)}
        />
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
        <Button variant="outline" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" />
          Atrás
        </Button>

        <Dialog open={showConfirm} onOpenChange={setShowConfirm}>
          <Button
            disabled={!name.trim() || audienceCount === 0 || !connectionId}
            onClick={() => setShowConfirm(true)}
          >
            <Save className="h-4 w-4" />
            {scheduledAt ? 'Programar difusión' : 'Crear borrador'}
          </Button>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Confirmar difusión</DialogTitle>
              <DialogDescription>
                Estás a punto de{' '}
                {scheduledAt ? 'programar' : 'crear como borrador'} esta
                difusión para{' '}
                <span className="font-medium text-foreground">
                  {audienceCount.toLocaleString()}
                </span>{' '}
                contactos usando la plantilla{' '}
                <span className="font-medium text-foreground">
                  {template.name}
                </span>
                . No se enviarán mensajes todavía.
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button variant="outline" onClick={() => setShowConfirm(false)}>
                Cancelar
              </Button>
              <Button
                onClick={() => {
                  setShowConfirm(false);
                  onSend();
                }}
              >
                <CalendarClock className="h-4 w-4" />
                {scheduledAt ? 'Programar difusión' : 'Crear borrador'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}
