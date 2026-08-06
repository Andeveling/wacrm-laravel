import { Check, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { Deal, DealStatus, PipelineStage } from '../contracts';
import { mockDealContacts } from '../fixtures';

interface DealFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  deal?: Deal | null;
  pipelineId: string;
  stages: PipelineStage[];
  defaultStageId?: string;
  onSaved: (deal: Deal) => void;
  onDeleted: (dealId: string) => void;
}

const CONTACTS = mockDealContacts(20);

export function DealForm({
  open,
  onOpenChange,
  deal,
  pipelineId,
  stages,
  defaultStageId,
  onSaved,
  onDeleted,
}: DealFormProps) {
  const [title, setTitle] = useState('');
  const [value, setValue] = useState('');
  const [contactId, setContactId] = useState('');
  const [stageId, setStageId] = useState('');
  const [expectedCloseDate, setExpectedCloseDate] = useState('');
  const [notes, setNotes] = useState('');
  const [confirmDelete, setConfirmDelete] = useState(false);

  useEffect(() => {
    if (!open) return;
    setConfirmDelete(false);
    if (deal) {
      setTitle(deal.title);
      setValue(String(deal.value ?? ''));
      setContactId(deal.contact_id ?? '');
      setStageId(deal.stage_id);
      setExpectedCloseDate(deal.expected_close_date ?? '');
      setNotes(deal.notes ?? '');
    } else {
      setTitle('');
      setValue('');
      setContactId('');
      setStageId(defaultStageId || stages[0]?.id || '');
      setExpectedCloseDate('');
      setNotes('');
    }
  }, [open, deal, defaultStageId, stages]);

  function handleSave() {
    if (!title.trim() || !contactId || !stageId) {
      toast.error('Título, contacto y etapa son obligatorios.');
      return;
    }

    const contact = CONTACTS.find((c) => c.id === contactId);
    const saved: Deal = {
      id: deal?.id ?? `deal-${Date.now()}`,
      pipeline_id: pipelineId,
      stage_id: stageId,
      contact_id: contactId,
      contact,
      title: title.trim(),
      value: Number.parseFloat(value) || 0,
      currency: deal?.currency ?? 'COP',
      notes: notes.trim() || undefined,
      expected_close_date: expectedCloseDate || undefined,
      status: deal?.status ?? 'open',
      created_at: deal?.created_at ?? new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    toast.success(deal ? 'Negocio actualizado.' : 'Negocio creado.');
    onOpenChange(false);
    onSaved(saved);
  }

  function handleStatusChange(status: DealStatus) {
    if (!deal) return;
    onSaved({ ...deal, status, updated_at: new Date().toISOString() });
    toast.success(
      status === 'won'
        ? 'Marcado como ganado.'
        : status === 'lost'
          ? 'Marcado como perdido.'
          : 'Reabierto.',
    );
    onOpenChange(false);
  }

  function handleDelete() {
    if (!deal) return;
    onDeleted(deal.id);
    toast.success('Negocio eliminado.');
    setConfirmDelete(false);
    onOpenChange(false);
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full p-0 sm:max-w-lg">
        <div className="flex h-full flex-col">
          <SheetHeader className="border-b p-4">
            <SheetTitle>{deal ? 'Editar negocio' : 'Nuevo negocio'}</SheetTitle>
          </SheetHeader>

          <div className="flex-1 space-y-4 overflow-y-auto p-4">
            <div className="grid gap-2">
              <Label>Título</Label>
              <Input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Nombre del negocio"
              />
            </div>

            <div className="grid gap-2">
              <Label>Contacto</Label>
              <select
                value={contactId}
                onChange={(e) => setContactId(e.target.value)}
                className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
              >
                <option value="">Selecciona un contacto</option>
                {CONTACTS.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name || c.phone}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Valor</Label>
                <Input
                  type="number"
                  value={value}
                  onChange={(e) => setValue(e.target.value)}
                  placeholder="0"
                />
              </div>
              <div className="grid gap-2">
                <Label>Etapa</Label>
                <select
                  value={stageId}
                  onChange={(e) => setStageId(e.target.value)}
                  className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                >
                  {stages.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid gap-2">
              <Label>Fecha estimada de cierre</Label>
              <Input
                type="date"
                value={expectedCloseDate}
                onChange={(e) => setExpectedCloseDate(e.target.value)}
              />
            </div>

            <div className="grid gap-2">
              <Label>Notas</Label>
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={4}
                placeholder="Notas internas…"
              />
            </div>

            {deal && (
              <div className="flex flex-wrap gap-2 border-t pt-4">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleStatusChange('won')}
                  disabled={deal.status === 'won'}
                >
                  <Check className="size-4" />
                  Marcar ganado
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleStatusChange('lost')}
                  disabled={deal.status === 'lost'}
                >
                  <X className="size-4" />
                  Marcar perdido
                </Button>
                {confirmDelete ? (
                  <Button
                    variant="destructive"
                    size="sm"
                    onClick={handleDelete}
                  >
                    Confirmar eliminación
                  </Button>
                ) : (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setConfirmDelete(true)}
                  >
                    <Trash2 className="size-4" />
                    Eliminar
                  </Button>
                )}
              </div>
            )}
          </div>

          <div className="flex justify-end gap-2 border-t p-4">
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSave}>
              {deal ? 'Actualizar' : 'Crear'}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
