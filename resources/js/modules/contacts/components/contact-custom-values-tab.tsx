import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useId } from 'react';
import { toast } from 'sonner';
import storeContactCustomValues from '@/actions/App/Domain/Contacts/Actions/StoreContactCustomValues';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import type { CustomField } from '../types';

interface ContactCustomValuesTabProps {
  contactId: string;
  fields: CustomField[];
  values: Record<string, string | null> | undefined;
  canWrite: boolean;
  onChanged: () => void;
}

export function ContactCustomValuesTab({
  contactId,
  fields,
  values,
  canWrite,
  onChanged,
}: ContactCustomValuesTabProps) {
  if (values === undefined) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
      </div>
    );
  }

  if (fields.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        No hay campos personalizados definidos.
      </p>
    );
  }

  return (
    <CustomValuesForm
      contactId={contactId}
      fields={fields}
      values={values}
      canWrite={canWrite}
      onChanged={onChanged}
    />
  );
}

interface CustomValuesFormProps {
  contactId: string;
  fields: CustomField[];
  values: Record<string, string | null>;
  canWrite: boolean;
  onChanged: () => void;
}

function CustomValuesForm({
  contactId,
  fields,
  values,
  canWrite,
  onChanged,
}: CustomValuesFormProps) {
  const fieldId = useId();
  const form = useForm<Record<string, string>>(
    Object.fromEntries(
      fields.map((field) => [field.id, values[field.id] ?? '']),
    ),
  );

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    form.transform((data) => ({ values: data }));
    form.submit(storeContactCustomValues(contactId), {
      preserveScroll: true,
      onSuccess: () => {
        onChanged();
        toast.success('Campos guardados.');
      },
      onError: () => toast.error('No se pudieron guardar los campos.'),
    });
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3">
      {fields.map((field) => (
        <div key={field.id} className="space-y-1.5">
          <Label htmlFor={`${fieldId}-${field.id}`} className="text-xs">
            {field.field_name}
          </Label>
          <Input
            id={`${fieldId}-${field.id}`}
            value={form.data[field.id] ?? ''}
            onChange={(event) => form.setData(field.id, event.target.value)}
            disabled={!canWrite}
            className="h-8 text-sm"
          />
        </div>
      ))}
      {canWrite ? (
        <Button type="submit" size="sm" disabled={form.processing}>
          <Save className="size-4" />
          Guardar campos
        </Button>
      ) : null}
    </form>
  );
}
