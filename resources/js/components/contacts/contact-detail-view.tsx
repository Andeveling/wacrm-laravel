import { useForm } from '@inertiajs/react';
import { Building2, Check, Copy, Mail, Phone, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
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
import type { Contact, Tag } from '@/types';

interface ContactDetailViewProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact: Contact;
  tags: Tag[];
  onUpdated: (contact: Contact) => void;
}

export function ContactDetailView({
  open,
  onOpenChange,
  contact,
  tags,
  onUpdated,
}: ContactDetailViewProps) {
  const [copiedPhone, setCopiedPhone] = useState(false);
  const form = useForm({
    name: '',
    phone: '',
    email: '',
    company: '',
    tag_ids: [] as string[],
  });

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

  const selectedContact = contact;

  function copyPhone() {
    void navigator.clipboard.writeText(selectedContact.phone);
    setCopiedPhone(true);
  }

  function saveDetails() {
    if (!form.data.phone.trim()) {
      toast.error('El teléfono es obligatorio.');
      return;
    }

    form.submit(update(selectedContact.id), {
      preserveScroll: true,
      onSuccess: () => {
        onUpdated({
          ...selectedContact,
          name: form.data.name.trim() || undefined,
          phone: form.data.phone.trim(),
          email: form.data.email.trim() || undefined,
          company: form.data.company.trim() || undefined,
          tags: tags.filter((tag) => form.data.tag_ids.includes(tag.id)),
          updated_at: new Date().toISOString(),
        });
        toast.success('Contacto actualizado.');
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

  function getInitials(name?: string) {
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
                <SheetTitle className="truncate">
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

          <Tabs defaultValue="details" className="flex min-h-0 flex-1 flex-col">
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

            {(['notes', 'custom', 'deals'] as const).map((tab) => (
              <TabsContent
                key={tab}
                value={tab}
                className="flex-1 px-4 py-3 text-sm text-muted-foreground"
              >
                Sin información registrada.
              </TabsContent>
            ))}
          </Tabs>
        </div>
      </SheetContent>
    </Sheet>
  );
}
