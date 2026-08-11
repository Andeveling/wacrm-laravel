import { router, useForm } from '@inertiajs/react';
import { Building2, Check, Copy, Mail, Phone, Save } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import showContactCustomValues from '@/actions/App/Domain/Contacts/Actions/ShowContactCustomValues';
import showContactDeals from '@/actions/App/Domain/Contacts/Actions/ShowContactDeals';
import showContactNotes from '@/actions/App/Domain/Contacts/Actions/ShowContactNotes';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
  Contact,
  ContactDeal,
  ContactNote,
  CustomField,
  Tag,
} from '../contracts';
import { ContactCustomValuesTab } from './contact-custom-values-tab';
import { ContactDealsTab } from './contact-deals-tab';
import { ContactNotesTab } from './contact-notes-tab';
import { TagPicker } from './tag-picker';

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

/**
 * Tabs whose data is not part of the contacts page payload. Each one is
 * fetched from its own endpoint the first time it is opened, so `notes`,
 * `customValues` and `contactDeals` can still hold the previous
 * contact's payload until that request lands.
 */
type LazyTab = 'notes' | 'custom' | 'deals';

const LAZY_TAB_ROUTE = {
  notes: showContactNotes,
  custom: showContactCustomValues,
  deals: showContactDeals,
} as const;

const LAZY_TAB_PROP = {
  notes: 'notes',
  custom: 'customValues',
  deals: 'contactDeals',
} as const;

function initials(name: string | null) {
  if (!name) {
    return '?';
  }

  return name
    .split(' ')
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

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
  const fieldId = useId();
  const [copiedPhone, setCopiedPhone] = useState(false);
  const [loadedTabs, setLoadedTabs] = useState<ReadonlySet<LazyTab>>(new Set());
  const requestedTabs = useRef<Set<LazyTab>>(new Set());
  const form = useForm({
    name: contact.name ?? '',
    phone: contact.phone,
    email: contact.email ?? '',
    company: contact.company ?? '',
    tag_ids: contact.tags.map((tag) => tag.id),
  });

  function loadTab(tab: LazyTab, force = false) {
    if (!force && requestedTabs.current.has(tab)) {
      return;
    }
    requestedTabs.current.add(tab);

    router.visit(LAZY_TAB_ROUTE[tab](contact.id).url, {
      only: [LAZY_TAB_PROP[tab]],
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => setLoadedTabs((previous) => new Set(previous).add(tab)),
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
      preserveState: true,
      onSuccess: () => {
        router.reload({
          only: ['contacts', 'filters', 'tags'],
          onSuccess: () => toast.success('Contacto actualizado.'),
        });
      },
      onError: () => toast.error('No se pudo actualizar el contacto.'),
    });
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full p-0 sm:max-w-lg">
        <div className="flex h-full flex-col">
          <SheetHeader className="border-b p-4">
            <div className="flex items-center gap-3">
              <Avatar className="size-12">
                <AvatarFallback className="bg-primary/10 text-sm font-medium text-primary">
                  {initials(contact.name)}
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
                  {contact.email ? (
                    <span className="flex items-center gap-1">
                      <Mail className="size-3" />
                      {contact.email}
                    </span>
                  ) : null}
                  {contact.company ? (
                    <span className="flex items-center gap-1">
                      <Building2 className="size-3" />
                      {contact.company}
                    </span>
                  ) : null}
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
                  <Label htmlFor={`${fieldId}-name`} className="text-xs">
                    Nombre
                  </Label>
                  <Input
                    id={`${fieldId}-name`}
                    data-testid="contact-detail-name"
                    value={form.data.name}
                    onChange={(event) =>
                      form.setData('name', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor={`${fieldId}-phone`} className="text-xs">
                    Teléfono <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id={`${fieldId}-phone`}
                    value={form.data.phone}
                    onChange={(event) =>
                      form.setData('phone', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor={`${fieldId}-email`} className="text-xs">
                    Correo
                  </Label>
                  <Input
                    id={`${fieldId}-email`}
                    value={form.data.email}
                    onChange={(event) =>
                      form.setData('email', event.target.value)
                    }
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor={`${fieldId}-company`} className="text-xs">
                    Empresa
                  </Label>
                  <Input
                    id={`${fieldId}-company`}
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
              <TagPicker
                tags={tags}
                selectedIds={form.data.tag_ids}
                onChange={(tagIds) => form.setData('tag_ids', tagIds)}
              />
              <Button className="mt-4" size="sm" onClick={saveDetails}>
                <Save className="size-4" />
                Guardar etiquetas
              </Button>
            </TabsContent>

            <TabsContent
              value="notes"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ContactNotesTab
                contactId={contact.id}
                notes={loadedTabs.has('notes') ? notes : undefined}
                canWrite={canWrite}
                onChanged={() => loadTab('notes', true)}
              />
            </TabsContent>

            <TabsContent
              value="custom"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ContactCustomValuesTab
                contactId={contact.id}
                fields={customFields}
                values={loadedTabs.has('custom') ? customValues : undefined}
                canWrite={canWrite}
                onChanged={() => loadTab('custom', true)}
              />
            </TabsContent>

            <TabsContent
              value="deals"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ContactDealsTab
                deals={loadedTabs.has('deals') ? contactDeals : undefined}
              />
            </TabsContent>
          </Tabs>
        </div>
      </SheetContent>
    </Sheet>
  );
}
