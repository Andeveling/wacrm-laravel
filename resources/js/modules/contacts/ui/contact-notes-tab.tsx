import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import destroyContactNote from '@/actions/App/Domain/Contacts/Actions/DestroyContactNote';
import storeContactNote from '@/actions/App/Domain/Contacts/Actions/StoreContactNote';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import type { ContactNote } from '../contracts';

interface ContactNotesTabProps {
  contactId: string;
  notes: ContactNote[] | undefined;
  canWrite: boolean;
  onChanged: () => void;
}

function formatNoteDate(value: string) {
  return new Date(value).toLocaleDateString('es-CO', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function ContactNotesTab({
  contactId,
  notes,
  canWrite,
  onChanged,
}: ContactNotesTabProps) {
  const form = useForm({ note_text: '' });

  function addNote(event: React.FormEvent) {
    event.preventDefault();

    if (!form.data.note_text.trim()) {
      return;
    }

    form.submit(storeContactNote(contactId), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onChanged();
        toast.success('Nota agregada.');
      },
      onError: () => toast.error('No se pudo agregar la nota.'),
    });
  }

  function deleteNote(note: ContactNote) {
    router.delete(destroyContactNote(note.id), {
      preserveScroll: true,
      onSuccess: () => {
        onChanged();
        toast.success('Nota eliminada.');
      },
      onError: () => toast.error('No se pudo eliminar la nota.'),
    });
  }

  if (notes === undefined) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-16 w-full" />
        <Skeleton className="h-16 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {canWrite ? (
        <form onSubmit={addNote} className="space-y-2">
          <Textarea
            placeholder="Escribe una nota…"
            value={form.data.note_text}
            onChange={(event) => form.setData('note_text', event.target.value)}
            className="min-h-16 text-sm"
          />
          <Button
            type="submit"
            size="sm"
            disabled={!form.data.note_text.trim() || form.processing}
          >
            Agregar nota
          </Button>
        </form>
      ) : null}

      {notes.length === 0 ? (
        <p className="text-sm text-muted-foreground">Sin notas registradas.</p>
      ) : (
        <ul className="space-y-2">
          {notes.map((note) => (
            <li
              key={note.id}
              className="rounded-lg border bg-muted/40 p-3 text-sm"
            >
              <p className="whitespace-pre-wrap">{note.note_text}</p>
              <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                <span>
                  {note.user.name ?? 'Usuario'}
                  {note.created_at
                    ? ` · ${formatNoteDate(note.created_at)}`
                    : ''}
                </span>
                {canWrite ? (
                  <button
                    type="button"
                    onClick={() => deleteNote(note)}
                    aria-label="Eliminar nota"
                    className="text-muted-foreground hover:text-destructive"
                  >
                    <Trash2 className="size-3.5" />
                  </button>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
