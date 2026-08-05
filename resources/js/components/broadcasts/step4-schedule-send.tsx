import { ArrowLeft, Send, Users } from 'lucide-react';
import { useState } from 'react';
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
import { MOCK_TAGS, mockContacts } from '@/lib/contacts/mock';
import type { MessageTemplate } from '@/types';
import type { AudienceConfig } from './step2-select-audience';

interface Step4Props {
  name: string;
  onNameChange: (name: string) => void;
  template: MessageTemplate;
  audience: AudienceConfig;
  onSend: () => void;
  onBack: () => void;
}

function estimateReach(audience: AudienceConfig): number {
  const all = mockContacts(24);
  if (audience.type === 'all') return all.length;
  if (!audience.tagIds?.length) return 0;
  return all.filter((c) =>
    (c.tags ?? []).some((t) => audience.tagIds?.includes(t.id)),
  ).length;
}

export function Step4ScheduleSend({
  name,
  onNameChange,
  template,
  audience,
  onSend,
  onBack,
}: Step4Props) {
  const [showConfirm, setShowConfirm] = useState(false);
  const estimatedReach = estimateReach(audience);
  const audienceLabel =
    audience.type === 'all'
      ? 'Todos los contactos'
      : `Por etiqueta (${(audience.tagIds ?? []).map((id) => MOCK_TAGS.find((t) => t.id === id)?.name).join(', ')})`;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-foreground">
          Revisa y envía
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Confirma los detalles antes de enviar.
        </p>
      </div>

      <div>
        <label
          htmlFor="broadcast-name"
          className="mb-1.5 block text-sm font-medium text-foreground"
        >
          Nombre de la difusión
        </label>
        <Input
          id="broadcast-name"
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
                {estimatedReach.toLocaleString()}
              </p>
            </div>
          </div>
          <div>
            <p className="text-xs text-muted-foreground">Idioma</p>
            <p className="text-foreground">{template.language ?? 'es_CO'}</p>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
        <Button variant="outline" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" />
          Atrás
        </Button>

        <Dialog open={showConfirm} onOpenChange={setShowConfirm}>
          <Button disabled={!name.trim()} onClick={() => setShowConfirm(true)}>
            <Send className="h-4 w-4" />
            Enviar ahora
          </Button>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Confirmar difusión</DialogTitle>
              <DialogDescription>
                Estás a punto de enviar esta difusión a{' '}
                <span className="font-medium text-foreground">
                  {estimatedReach.toLocaleString()}
                </span>{' '}
                contactos usando la plantilla{' '}
                <span className="font-medium text-foreground">
                  {template.name}
                </span>
                . Esta acción no se puede deshacer.
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
                <Send className="h-4 w-4" />
                Enviar ahora
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}
