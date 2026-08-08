import { router, useForm } from '@inertiajs/react';
import {
  Building2,
  Check,
  Copy,
  ExternalLink,
  Mail,
  Phone,
  Save,
  Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import destroyContactNote from '@/actions/App/Domain/Contacts/Actions/DestroyContactNote';
import showContactCustomValues from '@/actions/App/Domain/Contacts/Actions/ShowContactCustomValues';
import showContactDeals from '@/actions/App/Domain/Contacts/Actions/ShowContactDeals';
import showContactNotes from '@/actions/App/Domain/Contacts/Actions/ShowContactNotes';
import storeContactCustomValues from '@/actions/App/Domain/Contacts/Actions/StoreContactCustomValues';
import storeContactNote from '@/actions/App/Domain/Contacts/Actions/StoreContactNote';
import update from '@/actions/App/Domain/Contacts/Actions/UpdateContact';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/currency';
import { pipelines } from '@/routes';
import type {
  Contact,
  ContactDeal,
  ContactNote,
  CustomField,
  Tag,
} from '../contracts';

interface ContactDetailViewProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact: Contact;
  tags: Tag[];
  customFields: CustomField[];
  notes?: ContactNote[];
  customValues?: Record<string, string | null>;
  contactDeals?: ContactDeal[];
  canWrite: boolean;
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-CO', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const DEAL_STATUS_LABEL: Record<string, string> = {
  open: 'Abierto',
  won: 'Ganado',
  lost: 'Perdido',
};

export function ContactDetailView({
  open,
  onOpenChange,
  contact,
  tags,
  customFields,
  notes,
  customValues,
  contactDeals,
  canWrite,
}: ContactDetailViewProps) {
  const [copiedPhone, setCopiedPhone] = useState(false);
  const [readyTabs, setReadyTabs] = useState<Set<string>>(new Set());
  const loadedTabsRef = useRef<Set<string>>(new Set());
  const form = useForm({
    name: '',
    phone: '',
    email: '',
    company: '',
    tag_ids: [] as string[],
  });
  const noteForm = useForm({ note_text: '' });
  const customValuesForm = useForm<Record<string, string>>({});

  useEffect(() => {
    if (!open || !contact) return;

    form.setData({
      name: contact.name ?? '',
      phone: contact.phone,
      email: contact.email ?? '',
      company: contact.company ?? '',
      tag_ids: contact.tags?.map((tag) => tag.id) ?? [],
    });
  }, [open, contact, form.setData]);

  // biome-ignore lint/correctness/useExhaustiveDependencies: customValuesForm.setData is a stable reference from useForm
  useEffect(() => {
    customValuesForm.setData(
      Object.fromEntries(
        customFields.map((field) => [field.id, customValues?.[field.id] ?? '']),
      ),
    );
  }, [customValues, customFields]);

  function loadTab(tab: 'notes' | 'custom' | 'deals', force = false) {
    if (!force && loadedTabsRef.current.has(tab)) return;
    loadedTabsRef.current.add(tab);

    const routeByTab = {
      notes: showContactNotes,
      custom: showContactCustomValues,
      deals: showContactDeals,
    } as const;
    const onlyByTab = {
      notes: 'notes',
      custom: 'customValues',
      deals: 'contactDeals',
    } as const;

    router.visit(routeByTab[tab](contact.id).url, {
      only: [onlyByTab[tab]],
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setReadyTabs((prev) => new Set(prev).add(tab)),
    });
  }

  function copyPhone() {
    void navigator.clipboard.writeText(contact.phone);
    setCopiedPhone(true);
  }

  function saveDetails() {
    if (!form.data.phone.trim()) {
      toast.error('El teléfono es obligatorio.');
      return;
    }

    form.submit(update(contact.id), {
      preserveScroll: true,
      preserveUrl: true,
      onSuccess: () => {
        router.reload({
          only: ['contacts', 'filters', 'tags'],
          onSuccess: () => toast.success('Contacto actualizado.'),
        });
      },
      onError: () => toast.error('No se pudo actualizar el contacto.'),
    });
  }

  function toggleTag(tagId: string) {
    form.setData(
      'tag_ids',
      form.data.tag_ids.includes(tagId)
        ? form.data.tag_ids.filter((id) => id !== tagId)
        : [...form.data.tag_ids, tagId],
    );
  }

  function addNote(event: React.FormEvent) {
    event.preventDefault();

    if (!noteForm.data.note_text.trim()) return;

    noteForm.submit(storeContactNote(contact.id), {
      preserveScroll: true,
      onSuccess: () => {
        noteForm.reset();
        loadTab('notes', true);
        toast.success('Nota agregada.');
      },
      onError: () => toast.error('No se pudo agregar la nota.'),
    });
  }

  function deleteNote(note: ContactNote) {
    router.delete(destroyContactNote(note.id), {
      preserveScroll: true,
      onSuccess: () => {
        loadTab('notes', true);
        toast.success('Nota eliminada.');
      },
      onError: () => toast.error('No se pudo eliminar la nota.'),
    });
  }

  function saveCustomValues(event: React.FormEvent) {
    event.preventDefault();

    customValuesForm.transform((data) => ({ values: data }));
    customValuesForm.submit(storeContactCustomValues(contact.id), {
      preserveScroll: true,
      onSuccess: () => {
        loadTab('custom', true);
        toast.success('Campos guardados.');
      },
      onError: () => toast.error('No se pudieron guardar los campos.'),
    });
  }

  function getInitials(name?: string | null) {
    if (!name) return '?';

    return name
      .split(' ')
      .map((word) => word[0])
      .join('')
      .toUpperCase()
      .slice(0, 2);
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full p-0 sm:max-w-lg">
        <div className="flex h-full flex-col">
          <SheetHeader className="border-b p-4">
            <div className="flex items-center gap-3">
              <Avatar className="size-12">
                <AvatarFallback className="bg-primary/10 text-sm font-medium text-primary">
                  {getInitials(contact.name)}
                </AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <SheetTitle
                  data-testid="contact-detail-title"
                  className="truncate"
                >
                  {contact.name || 'Sin nombre'}
                </SheetTitle>
                <SheetDescription className="mt-0.5 text-xs">
                  Detalles del contacto
                </SheetDescription>
                <div className="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                  <button
                    type="button"
                    onClick={copyPhone}
                    className="flex cursor-pointer items-center gap-1 hover:text-primary"
                  >
                    <Phone className="size-3" />
                    {contact.phone}
                    {copiedPhone ? (
                      <Check className="size-3 text-primary" />
                    ) : (
                      <Copy className="size-3" />
                    )}
                  </button>
                  {contact.email && (
                    <span className="flex items-center gap-1">
                      <Mail className="size-3" />
                      {contact.email}
                    </span>
                  )}
                  {contact.company && (
                    <span className="flex items-center gap-1">
                      <Building2 className="size-3" />
                      {contact.company}
                    </span>
                  )}
                </div>
              </div>
            </div>
          </SheetHeader>

          <Tabs
            defaultValue="details"
            className="flex min-h-0 flex-1 flex-col"
            onValueChange={(tab) => {
              if (tab === 'notes' || tab === 'custom' || tab === 'deals') {
                loadTab(tab);
              }
            }}
          >
            <TabsList className="mx-4 mt-3">
              <TabsTrigger value="details">Detalles</TabsTrigger>
              <TabsTrigger value="tags">Etiquetas</TabsTrigger>
              <TabsTrigger value="notes">Notas</TabsTrigger>
              <TabsTrigger value="custom">Campos</TabsTrigger>
              <TabsTrigger value="deals">Negocios</TabsTrigger>
            </TabsList>

            <TabsContent
              value="details"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <div className="space-y-3">
                <div className="space-y-1.5">
                  <Label className="text-xs">Nombre</Label>
                  <Input
                    data-testid="contact-detail-name"
                    value={form.data.name}
                    onChange={(event) =>
                      form.setData('name', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">
                    Teléfono <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={form.data.phone}
                    onChange={(event) =>
                      form.setData('phone', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">Correo</Label>
                  <Input
                    value={form.data.email}
                    onChange={(event) =>
                      form.setData('email', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">Empresa</Label>
                  <Input
                    value={form.data.company}
                    onChange={(event) =>
                      form.setData('company', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <Button
                  size="sm"
                  onClick={saveDetails}
                  disabled={form.processing}
                >
                  <Save className="size-4" />
                  {form.processing ? 'Guardando…' : 'Guardar cambios'}
                </Button>
              </div>
            </TabsContent>

            <TabsContent
              value="tags"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <div className="flex flex-wrap gap-1.5">
                {tags.map((tag) => {
                  const selected = form.data.tag_ids.includes(tag.id);

                  return (
                    <button
                      key={tag.id}
                      type="button"
                      onClick={() => toggleTag(tag.id)}
                      className={`cursor-pointer rounded-full px-2.5 py-0.5 text-xs font-medium transition-opacity ${
                        selected
                          ? 'ring-2 ring-primary ring-offset-1'
                          : 'opacity-60 hover:opacity-100'
                      }`}
                      style={{
                        backgroundColor: `${tag.color}20`,
                        color: tag.color,
                      }}
                    >
                      {tag.name}
                    </button>
                  );
                })}
              </div>
              <Button className="mt-4" size="sm" onClick={saveDetails}>
                <Save className="size-4" />
                Guardar etiquetas
              </Button>
            </TabsContent>

            <TabsContent
              value="notes"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              {!readyTabs.has('notes') || notes === undefined ? (
                <div className="space-y-2">
                  <Skeleton className="h-16 w-full" />
                  <Skeleton className="h-16 w-full" />
                </div>
              ) : (
                <div className="space-y-3">
                  {canWrite && (
                    <form onSubmit={addNote} className="space-y-2">
                      <Textarea
                        placeholder="Escribe una nota…"
                        value={noteForm.data.note_text}
                        onChange={(event) =>
                          noteForm.setData('note_text', event.target.value)
                        }
                        className="min-h-16 text-sm"
                      />
                      <Button
                        type="submit"
                        size="sm"
                        disabled={
                          !noteForm.data.note_text.trim() || noteForm.processing
                        }
                      >
                        Agregar nota
                      </Button>
                    </form>
                  )}

                  {notes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                      Sin notas registradas.
                    </p>
                  ) : (
                    <ul className="space-y-2">
                      {notes.map((note) => (
                        <li
                          key={note.id}
                          className="rounded-lg border bg-muted/40 p-3 text-sm"
                        >
                          <p className="whitespace-pre-wrap">
                            {note.note_text}
                          </p>
                          <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span>
                              {note.user.name ?? 'Usuario'}
                              {note.created_at
                                ? ` · ${formatDate(note.created_at)}`
                                : ''}
                            </span>
                            {canWrite && (
                              <button
                                type="button"
                                onClick={() => deleteNote(note)}
                                aria-label="Eliminar nota"
                                className="text-muted-foreground hover:text-destructive"
                              >
                                <Trash2 className="size-3.5" />
                              </button>
                            )}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </TabsContent>

            <TabsContent
              value="custom"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              {!readyTabs.has('custom') || customValues === undefined ? (
                <div className="space-y-2">
                  <Skeleton className="h-8 w-full" />
                  <Skeleton className="h-8 w-full" />
                </div>
              ) : customFields.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No hay campos personalizados definidos.
                </p>
              ) : (
                <form onSubmit={saveCustomValues} className="space-y-3">
                  {customFields.map((field) => (
                    <div key={field.id} className="space-y-1.5">
                      <Label className="text-xs">{field.field_name}</Label>
                      <Input
                        value={customValuesForm.data[field.id] ?? ''}
                        onChange={(event) =>
                          customValuesForm.setData(field.id, event.target.value)
                        }
                        disabled={!canWrite}
                        className="h-8 text-sm"
                      />
                    </div>
                  ))}
                  {canWrite && (
                    <Button
                      type="submit"
                      size="sm"
                      disabled={customValuesForm.processing}
                    >
                      <Save className="size-4" />
                      Guardar campos
                    </Button>
                  )}
                </form>
              )}
            </TabsContent>

            <TabsContent
              value="deals"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              {!readyTabs.has('deals') || contactDeals === undefined ? (
                <div className="space-y-2">
                  <Skeleton className="h-16 w-full" />
                  <Skeleton className="h-16 w-full" />
                </div>
              ) : contactDeals.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  Sin negocios registrados.
                </p>
              ) : (
                <div className="space-y-3">
                  <ul className="space-y-2">
                    {contactDeals.map((deal) => (
                      <li
                        key={deal.id}
                        className="rounded-lg border p-3 text-sm"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <span className="font-medium">{deal.title}</span>
                          <span className="text-xs text-muted-foreground">
                            {deal.status
                              ? DEAL_STATUS_LABEL[deal.status]
                              : null}
                          </span>
                        </div>
                        <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                          <span>{deal.stage?.name ?? 'Sin etapa'}</span>
                          <span className="font-semibold text-primary">
                            {formatCurrency(
                              Number(deal.value),
                              deal.currency ?? undefined,
                            )}
                          </span>
                        </div>
                      </li>
                    ))}
                  </ul>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => router.visit(pipelines().url)}
                  >
                    <ExternalLink className="size-4" />
                    Ver en Pipelines
                  </Button>
                </div>
              )}
            </TabsContent>
          </Tabs>
        </div>
      </SheetContent>
    </Sheet>
  );
}
