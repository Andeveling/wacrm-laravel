import { Head } from '@inertiajs/react';
import { MessageSquare, Pencil, Plus, Trash2 } from 'lucide-react';
import { useId, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { overview as settingsOverview } from '@/routes/settings';
import type { QuickReply } from '../types';

function mockQuickReplies(): QuickReply[] {
  return [
    {
      id: 'qr-1',
      title: 'Horario de atención',
      content_text: 'Atendemos de lunes a viernes de 8am a 6pm.',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
    {
      id: 'qr-2',
      title: 'Saludo inicial',
      content_text: '¡Hola! Gracias por escribirnos, ¿en qué podemos ayudarte?',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    },
  ];
}

interface Draft {
  id?: string;
  title: string;
  content_text: string;
}

export default function QuickReplies() {
  const qrTitleId = useId();
  const [items, setItems] = useState<QuickReply[]>(() => mockQuickReplies());
  const [draft, setDraft] = useState<Draft | null>(null);

  function openCreate() {
    setDraft({ title: '', content_text: '' });
  }

  function openEdit(qr: QuickReply) {
    setDraft({ id: qr.id, title: qr.title, content_text: qr.content_text });
  }

  function save() {
    if (!draft) return;
    if (!draft.title.trim()) {
      toast.error('Dale un nombre a la respuesta rápida.');
      return;
    }

    if (draft.id) {
      setItems((prev) =>
        prev.map((qr) =>
          qr.id === draft.id
            ? {
                ...qr,
                title: draft.title,
                content_text: draft.content_text,
                updated_at: new Date().toISOString(),
              }
            : qr,
        ),
      );
      toast.success('Respuesta rápida actualizada.');
    } else {
      setItems((prev) => [
        ...prev,
        {
          id: `qr-${Date.now()}`,
          title: draft.title,
          content_text: draft.content_text,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ]);
      toast.success('Respuesta rápida creada.');
    }
    setDraft(null);
  }

  function remove(id: string) {
    setItems((prev) => prev.filter((qr) => qr.id !== id));
    toast.success('Respuesta rápida eliminada.');
  }

  return (
    <>
      <Head title="Respuestas rápidas" />

      <div className="space-y-6">
        <div className="flex items-start justify-between gap-3">
          <Heading
            title="Respuestas rápidas"
            description="Atajos reutilizables que los agentes pueden insertar desde el composer del inbox."
          />
          <Button onClick={openCreate} className="shrink-0">
            <Plus className="h-4 w-4" />
            Nueva respuesta
          </Button>
        </div>

        {items.length === 0 ? (
          <p className="rounded-lg border border-dashed border-border py-10 text-center text-sm text-muted-foreground">
            Sin respuestas rápidas todavía. Crea una para reutilizarla en las
            conversaciones.
          </p>
        ) : (
          <ul className="flex flex-col gap-2">
            {items.map((qr) => (
              <li
                key={qr.id}
                className="flex items-start gap-3 rounded-lg border bg-card p-3"
              >
                <MessageSquare className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-foreground">
                    {qr.title}
                  </p>
                  <p className="truncate text-xs text-muted-foreground">
                    {qr.content_text}
                  </p>
                </div>
                <div className="flex shrink-0 gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => openEdit(qr)}
                  >
                    <Pencil className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => remove(qr.id)}
                    className="text-destructive hover:bg-destructive/10"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      <Dialog open={!!draft} onOpenChange={(o) => !o && setDraft(null)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>
              {draft?.id ? 'Editar respuesta rápida' : 'Nueva respuesta rápida'}
            </DialogTitle>
          </DialogHeader>
          {draft ? (
            <div className="space-y-3">
              <div>
                <label
                  htmlFor={qrTitleId}
                  className="mb-1 block text-xs text-muted-foreground"
                >
                  Nombre
                </label>
                <Input
                  id={qrTitleId}
                  value={draft.title}
                  onChange={(e) =>
                    setDraft({ ...draft, title: e.target.value })
                  }
                  placeholder="Ej. Horario de atención"
                />
              </div>
              <Textarea
                value={draft.content_text}
                onChange={(e) =>
                  setDraft({ ...draft, content_text: e.target.value })
                }
                placeholder="El texto del mensaje a insertar"
                className="min-h-28"
              />
            </div>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setDraft(null)}>
              Cancelar
            </Button>
            <Button onClick={save}>Guardar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

QuickReplies.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'Respuestas rápidas' },
  ],
};
