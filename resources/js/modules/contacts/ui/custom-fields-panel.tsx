import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CustomField } from '../contracts';
import { mockCustomFields } from '../fixtures';

function isDuplicate(
  fields: CustomField[],
  name: string,
  exceptId?: string,
): boolean {
  const lower = name.toLowerCase();
  return fields.some(
    (f) => f.id !== exceptId && f.field_name.toLowerCase() === lower,
  );
}

export function CustomFieldsPanel() {
  const [fields, setFields] = useState<CustomField[]>(() => mockCustomFields());
  const [newName, setNewName] = useState('');

  function handleCreate() {
    const name = newName.trim();
    if (!name) return;
    if (isDuplicate(fields, name)) {
      toast.error(`Ya existe un campo llamado «${name}».`);
      return;
    }
    const field: CustomField = {
      id: `field-${Date.now()}`,
      field_name: name,
      field_type: 'text',
      created_at: new Date().toISOString(),
    };
    setFields((prev) => [...prev, field]);
    setNewName('');
    toast.success(`Campo «${name}» creado.`);
  }

  function handleRename(field: CustomField, nextName: string): boolean {
    const name = nextName.trim();
    if (!name || name === field.field_name) return true;
    if (isDuplicate(fields, name, field.id)) {
      toast.error(`Ya existe un campo llamado «${name}».`);
      return false;
    }
    setFields((prev) =>
      prev.map((f) => (f.id === field.id ? { ...f, field_name: name } : f)),
    );
    return true;
  }

  function handleDelete(field: CustomField) {
    setFields((prev) => prev.filter((f) => f.id !== field.id));
    toast.success(`Campo «${field.field_name}» eliminado.`);
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Input
          value={newName}
          onChange={(e) => setNewName(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              handleCreate();
            }
          }}
          placeholder="Nombre del campo"
        />
        <Button
          onClick={handleCreate}
          disabled={!newName.trim()}
          className="shrink-0"
        >
          <Plus className="size-4" />
          Agregar campo
        </Button>
      </div>

      <div className="max-h-72 overflow-y-auto rounded-md border">
        {fields.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            Sin campos personalizados todavía.
          </p>
        ) : (
          <ul className="divide-y">
            {fields.map((field) => (
              <FieldRow
                key={field.id}
                field={field}
                onRename={handleRename}
                onDelete={handleDelete}
              />
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}

function FieldRow({
  field,
  onRename,
  onDelete,
}: {
  field: CustomField;
  onRename: (field: CustomField, name: string) => boolean;
  onDelete: (field: CustomField) => void;
}) {
  const [name, setName] = useState(field.field_name);
  const [busy, setBusy] = useState(false);

  function commit() {
    if (name.trim() === field.field_name) {
      setName(field.field_name);
      return;
    }
    setBusy(true);
    const ok = onRename(field, name);
    setBusy(false);
    if (!ok) setName(field.field_name);
  }

  return (
    <li className="flex items-center gap-2 px-3 py-2">
      <Input
        value={name}
        disabled={busy}
        onChange={(e) => setName(e.target.value)}
        onBlur={commit}
        onKeyDown={(e) => {
          if (e.key === 'Enter') e.currentTarget.blur();
        }}
        aria-label={`Renombrar ${field.field_name}`}
        className="h-8 border-transparent bg-transparent hover:border-input focus:border-primary"
      />
      <Button
        variant="ghost"
        size="icon"
        disabled={busy}
        onClick={() => onDelete(field)}
        title="Eliminar campo"
        className="shrink-0 text-muted-foreground hover:text-destructive"
      >
        {busy ? (
          <Loader2 className="size-4 animate-spin" />
        ) : (
          <Trash2 className="size-4" />
        )}
      </Button>
    </li>
  );
}
