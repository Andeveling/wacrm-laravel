import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import destroyCustomField from '@/actions/App/Domain/Contacts/Actions/DestroyCustomField';
import storeCustomField from '@/actions/App/Domain/Contacts/Actions/StoreCustomField';
import updateCustomField from '@/actions/App/Domain/Contacts/Actions/UpdateCustomField';
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
import { Label } from '@/components/ui/label';
import type { CustomField } from '@/types';

interface CustomFieldsManagerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  fields: CustomField[];
  canManage: boolean;
  onChanged: () => void;
}

export function CustomFieldsManager({
  open,
  onOpenChange,
  fields,
  canManage,
  onChanged,
}: CustomFieldsManagerProps) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const form = useForm({
    field_name: '',
    field_type: 'text',
    field_options: [] as string[],
  });

  function startEditing(field: CustomField) {
    setEditingId(field.id);
    form.setData({
      field_name: field.field_name,
      field_type: field.field_type,
      field_options: [],
    });
  }

  function resetForm() {
    setEditingId(null);
    form.reset();
  }

  function saveField(event: React.FormEvent) {
    event.preventDefault();

    if (!form.data.field_name.trim()) {
      toast.error('El nombre del campo es obligatorio.');
      return;
    }

    const options = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editingId ? 'Campo actualizado.' : 'Campo creado.');
        resetForm();
        onChanged();
      },
      onError: () => toast.error('No se pudo guardar el campo.'),
    };

    if (editingId) {
      form.submit(updateCustomField(editingId), options);
    } else {
      form.submit(storeCustomField(), options);
    }
  }

  function removeField(field: CustomField) {
    form.submit(destroyCustomField(field.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Campo «${field.field_name}» eliminado.`);
        onChanged();
      },
      onError: () => toast.error('No se pudo eliminar el campo.'),
    });
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) resetForm();
        onOpenChange(nextOpen);
      }}
    >
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Campos personalizados</DialogTitle>
          <DialogDescription>
            Define los datos adicionales que puede guardar cada contacto.
          </DialogDescription>
        </DialogHeader>

        {fields.length > 0 ? (
          <div className="max-h-56 space-y-2 overflow-y-auto">
            {fields.map((field) => (
              <div
                key={field.id}
                className="flex items-center justify-between rounded-md border px-3 py-2"
              >
                <div>
                  <p className="text-sm font-medium">{field.field_name}</p>
                  <p className="text-xs text-muted-foreground">
                    Tipo: {field.field_type}
                  </p>
                </div>
                {canManage && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => startEditing(field)}
                    >
                      <Pencil className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => removeField(field)}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p className="py-4 text-center text-sm text-muted-foreground">
            Aún no hay campos personalizados.
          </p>
        )}

        {canManage && (
          <form onSubmit={saveField} className="space-y-3 border-t pt-4">
            <div className="space-y-2">
              <Label htmlFor="custom-field-name">
                {editingId ? 'Renombrar campo' : 'Nuevo campo'}
              </Label>
              <Input
                id="custom-field-name"
                value={form.data.field_name}
                onChange={(event) =>
                  form.setData('field_name', event.target.value)
                }
                placeholder="Ej. Fuente"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="custom-field-type">Tipo</Label>
              <select
                id="custom-field-type"
                value={form.data.field_type}
                onChange={(event) =>
                  form.setData('field_type', event.target.value)
                }
                className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                disabled={!!editingId}
              >
                <option value="text">Texto</option>
                <option value="number">Número</option>
                <option value="date">Fecha</option>
                <option value="select">Selección</option>
              </select>
            </div>
            <DialogFooter>
              {editingId && (
                <Button type="button" variant="outline" onClick={resetForm}>
                  Cancelar edición
                </Button>
              )}
              <Button type="submit" disabled={form.processing}>
                <Plus className="size-4" />
                {editingId ? 'Guardar' : 'Agregar campo'}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
