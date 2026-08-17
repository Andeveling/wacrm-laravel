import { type InertiaForm, router, useForm } from '@inertiajs/react';
import { Check, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import destroyDeal from '@/actions/App/Domain/Pipelines/Actions/DestroyDeal';
import storeDeal from '@/actions/App/Domain/Pipelines/Actions/StoreDeal';
import updateDeal from '@/actions/App/Domain/Pipelines/Actions/UpdateDeal';
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
import type { Deal, DealContact, DealStatus, PipelineStage } from '../types';

interface DealFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  deal?: Deal | null;
  pipelineId: string;
  stages: PipelineStage[];
  contacts: DealContact[];
  defaultStageId?: string;
}

type DealFormData = {
  title: string;
  value: string;
  contact_id: string;
  stage_id: string;
  currency: string;
  expected_close_date: string;
  notes: string;
  status: DealStatus;
};

type DealFormForm = InertiaForm<DealFormData>;
type DealFormField = Exclude<keyof DealFormData, 'status'>;

interface DealFormViewProps
  extends Omit<DealFormProps, 'pipelineId' | 'defaultStageId'> {
  form: DealFormForm;
  confirmDelete: boolean;
  handleSave: () => void;
  handleStatusChange: (status: DealStatus) => void;
  handleDelete: () => void;
  setConfirmDelete: (value: boolean) => void;
}

function DealFormView({
  open,
  onOpenChange,
  deal,
  stages,
  contacts,
  form,
  confirmDelete,
  handleSave,
  handleStatusChange,
  handleDelete,
  setConfirmDelete,
}: DealFormViewProps) {
  const field = (name: DealFormField) => (value: string) =>
    form.setData(name, value);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full p-0 sm:max-w-lg">
        <div className="flex h-full flex-col">
          <SheetHeader className="border-b p-4">
            <SheetTitle>{deal ? 'Editar negocio' : 'Nuevo negocio'}</SheetTitle>
          </SheetHeader>
          <div className="flex-1 space-y-4 overflow-y-auto p-4">
            <div className="grid gap-2">
              <Label htmlFor="deal-title">Título</Label>
              <Input
                id="deal-title"
                value={form.data.title}
                onChange={(event) => field('title')(event.target.value)}
                placeholder="Nombre del negocio"
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="deal-contact">Contacto</Label>
              <select
                id="deal-contact"
                value={form.data.contact_id}
                onChange={(event) => field('contact_id')(event.target.value)}
                className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
              >
                <option value="">Sin contacto</option>
                {contacts.map((contact) => (
                  <option key={contact.id} value={contact.id}>
                    {contact.name || contact.phone}
                  </option>
                ))}
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label htmlFor="deal-value">Valor</Label>
                <Input
                  id="deal-value"
                  type="number"
                  min="0"
                  value={form.data.value}
                  onChange={(event) => field('value')(event.target.value)}
                  placeholder="0"
                />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="deal-stage">Etapa</Label>
                <select
                  id="deal-stage"
                  value={form.data.stage_id}
                  onChange={(event) => field('stage_id')(event.target.value)}
                  className="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                >
                  {stages.map((stage) => (
                    <option key={stage.id} value={stage.id}>
                      {stage.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="grid gap-2">
              <Label htmlFor="deal-close-date">Fecha estimada de cierre</Label>
              <Input
                id="deal-close-date"
                type="date"
                value={form.data.expected_close_date}
                onChange={(event) =>
                  field('expected_close_date')(event.target.value)
                }
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="deal-notes">Notas</Label>
              <Textarea
                id="deal-notes"
                value={form.data.notes}
                onChange={(event) => field('notes')(event.target.value)}
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
            <Button onClick={handleSave} disabled={form.processing}>
              {form.processing ? 'Guardando…' : deal ? 'Actualizar' : 'Crear'}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
export function DealForm({
  open,
  onOpenChange,
  deal,
  pipelineId,
  stages,
  contacts,
  defaultStageId,
}: DealFormProps) {
  const [confirmDelete, setConfirmDelete] = useState(false);
  const form = useForm<DealFormData>({
    title: '',
    value: '',
    contact_id: '',
    stage_id: '',
    currency: 'COP',
    expected_close_date: '',
    notes: '',
    status: 'open' as DealStatus,
  });

  useEffect(() => {
    if (!open) return;
    setConfirmDelete(false);

    if (deal) {
      form.setData({
        title: deal.title,
        value: String(deal.value ?? ''),
        contact_id: deal.contact_id ?? '',
        stage_id: deal.stage_id,
        currency: deal.currency ?? 'COP',
        expected_close_date: deal.expected_close_date ?? '',
        notes: deal.notes ?? '',
        status: deal.status ?? 'open',
      });
      return;
    }

    form.setData({
      title: '',
      value: '',
      contact_id: '',
      stage_id: defaultStageId || stages[0]?.id || '',
      currency: 'COP',
      expected_close_date: '',
      notes: '',
      status: 'open',
    });
  }, [open, deal, defaultStageId, stages, form.setData]);

  function handleSave() {
    if (!form.data.title.trim() || !form.data.stage_id) {
      toast.error('Título y etapa son obligatorios.');
      return;
    }

    form.submit(deal ? updateDeal(deal.id) : storeDeal(pipelineId), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(deal ? 'Negocio actualizado.' : 'Negocio creado.');
        onOpenChange(false);
      },
      onError: () => toast.error('No se pudo guardar el negocio.'),
    });
  }

  function handleStatusChange(status: DealStatus) {
    if (!deal) return;

    router.patch(
      updateDeal(deal.id),
      { status },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            status === 'won'
              ? 'Marcado como ganado.'
              : status === 'lost'
                ? 'Marcado como perdido.'
                : 'Reabierto.',
          );
          onOpenChange(false);
        },
        onError: () => toast.error('No se pudo actualizar el estado.'),
      },
    );
  }

  function handleDelete() {
    if (!deal) return;

    router.delete(destroyDeal(deal.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Negocio eliminado.');
        setConfirmDelete(false);
        onOpenChange(false);
      },
      onError: () => toast.error('No se pudo eliminar el negocio.'),
    });
  }
  return (
    <DealFormView
      open={open}
      onOpenChange={onOpenChange}
      deal={deal}
      stages={stages}
      contacts={contacts}
      form={form}
      confirmDelete={confirmDelete}
      handleSave={handleSave}
      handleStatusChange={handleStatusChange}
      handleDelete={handleDelete}
      setConfirmDelete={setConfirmDelete}
    />
  );
}
