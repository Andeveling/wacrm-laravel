import { router, useForm } from '@inertiajs/react';
import { useId } from 'react';
import { toast } from 'sonner';
import store from '@/actions/App/Domain/Contacts/Actions/StoreContact';
import update from '@/actions/App/Domain/Contacts/Actions/UpdateContact';
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
import type { ContactFormProps } from '../contracts';
import { TagPicker } from './tag-picker';

export function ContactForm({
  open,
  onOpenChange,
  contact,
  tags,
}: ContactFormProps) {
  const isEdit = !!contact;
  const fieldId = useId();
  const form = useForm({
    name: contact?.name ?? '',
    phone: contact?.phone ?? '',
    email: contact?.email ?? '',
    company: contact?.company ?? '',
    tag_ids: contact?.tags.map((tag) => tag.id) ?? [],
  });

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    if (!form.data.phone.trim()) {
      toast.error('El teléfono es obligatorio.');
      return;
    }

    form.submit(isEdit ? update(contact.id) : store(), {
      preserveScroll: true,
      preserveUrl: isEdit,
      onSuccess: () => {
        onOpenChange(false);
        router.reload({
          only: ['contacts', 'filters', 'tags'],
          onSuccess: () =>
            toast.success(
              isEdit ? 'Contacto actualizado.' : 'Contacto creado.',
            ),
        });
      },
      onError: () => toast.error('No se pudo guardar el contacto.'),
    });
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {isEdit ? 'Editar contacto' : 'Nuevo contacto'}
          </DialogTitle>
          <DialogDescription>
            {isEdit
              ? 'Actualiza los datos del contacto.'
              : 'Agrega un nuevo contacto a tu lista.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor={`${fieldId}-name`}>Nombre</Label>
            <Input
              id={`${fieldId}-name`}
              data-testid="contact-form-name"
              value={form.data.name}
              onChange={(event) => form.setData('name', event.target.value)}
              placeholder="Nombre completo"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor={`${fieldId}-phone`}>
              Teléfono <span className="text-destructive">*</span>
            </Label>
            <Input
              id={`${fieldId}-phone`}
              data-testid="contact-form-phone"
              value={form.data.phone}
              onChange={(event) => form.setData('phone', event.target.value)}
              placeholder="+57 300 000 0000"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor={`${fieldId}-email`}>Correo</Label>
            <Input
              id={`${fieldId}-email`}
              data-testid="contact-form-email"
              type="email"
              value={form.data.email}
              onChange={(event) => form.setData('email', event.target.value)}
              placeholder="correo@ejemplo.com"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor={`${fieldId}-company`}>Empresa</Label>
            <Input
              id={`${fieldId}-company`}
              data-testid="contact-form-company"
              value={form.data.company}
              onChange={(event) => form.setData('company', event.target.value)}
              placeholder="Nombre de la empresa"
            />
          </div>

          <div className="space-y-2">
            <Label>Etiquetas</Label>
            <TagPicker
              tags={tags}
              selectedIds={form.data.tag_ids}
              onChange={(tagIds) => form.setData('tag_ids', tagIds)}
            />
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Guardando…' : isEdit ? 'Actualizar' : 'Crear'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
