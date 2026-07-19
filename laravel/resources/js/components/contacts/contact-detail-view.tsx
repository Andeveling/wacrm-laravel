import { Building2, Check, Copy, Mail, Phone, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { MOCK_TAGS } from '@/lib/contacts/mock';
import type { Contact } from '@/types';

interface ContactDetailViewProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact: Contact | null;
  onUpdated: (contact: Contact) => void;
}

export function ContactDetailView({
  open,
  onOpenChange,
  contact,
  onUpdated,
}: ContactDetailViewProps) {
  const [copiedPhone, setCopiedPhone] = useState(false);

  const [editName, setEditName] = useState('');
  const [editPhone, setEditPhone] = useState('');
  const [editEmail, setEditEmail] = useState('');
  const [editCompany, setEditCompany] = useState('');
  const [tagIds, setTagIds] = useState<string[]>([]);

  useEffect(() => {
    if (open && contact) {
      setEditName(contact.name ?? '');
      setEditPhone(contact.phone);
      setEditEmail(contact.email ?? '');
      setEditCompany(contact.company ?? '');
      setTagIds((contact.tags ?? []).map((tag) => tag.id));
    }
  }, [open, contact]);

  if (!contact) return null;
  const c = contact;

  function copyPhone() {
    navigator.clipboard.writeText(c.phone);
    setCopiedPhone(true);
    setTimeout(() => setCopiedPhone(false), 2000);
  }

  function saveDetails() {
    if (!editPhone.trim()) {
      toast.error('El teléfono es obligatorio.');
      return;
    }
    onUpdated({
      ...c,
      name: editName.trim() || undefined,
      phone: editPhone.trim(),
      email: editEmail.trim() || undefined,
      company: editCompany.trim() || undefined,
      updated_at: new Date().toISOString(),
    });
    toast.success('Contacto actualizado.');
  }

  function toggleTag(tagId: string) {
    const next = tagIds.includes(tagId)
      ? tagIds.filter((id) => id !== tagId)
      : [...tagIds, tagId];
    setTagIds(next);
    onUpdated({ ...c, tags: MOCK_TAGS.filter((tag) => next.includes(tag.id)) });
  }

  function getInitials(name?: string) {
    if (!name) return '?';
    return name
      .split(' ')
      .map((w) => w[0])
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
                    value={editName}
                    onChange={(e) => setEditName(e.target.value)}
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">
                    Teléfono <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    value={editPhone}
                    onChange={(e) => setEditPhone(e.target.value)}
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">Correo</Label>
                  <Input
                    value={editEmail}
                    onChange={(e) => setEditEmail(e.target.value)}
                    className="h-8 text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">Empresa</Label>
                  <Input
                    value={editCompany}
                    onChange={(e) => setEditCompany(e.target.value)}
                    className="h-8 text-sm"
                  />
                </div>
                <Button size="sm" onClick={saveDetails}>
                  <Save className="size-4" />
                  Guardar cambios
                </Button>
              </div>
            </TabsContent>

            <TabsContent
              value="tags"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <div className="flex flex-wrap gap-1.5">
                {MOCK_TAGS.map((tag) => {
                  const selected = tagIds.includes(tag.id);
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
            </TabsContent>

            <TabsContent
              value="notes"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ComingSoon />
            </TabsContent>
            <TabsContent
              value="custom"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ComingSoon />
            </TabsContent>
            <TabsContent
              value="deals"
              className="flex-1 overflow-y-auto px-4 py-3"
            >
              <ComingSoon />
            </TabsContent>
          </Tabs>
        </div>
      </SheetContent>
    </Sheet>
  );
}

function ComingSoon() {
  return (
    <div className="flex flex-col items-center gap-2 py-8 text-center">
      <Badge variant="outline">Próximamente</Badge>
      <p className="text-sm text-muted-foreground">
        Esta sección aún no está disponible.
      </p>
    </div>
  );
}
