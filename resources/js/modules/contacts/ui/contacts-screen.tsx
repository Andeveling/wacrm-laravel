import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import bulkDestroy from '@/actions/App/Domain/Contacts/Actions/BulkDestroyContacts';
import destroy from '@/actions/App/Domain/Contacts/Actions/DestroyContact';
import exportContacts from '@/actions/App/Domain/Contacts/Actions/ExportContacts';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useDialog } from '@/hooks/use-dialog';
import { contacts } from '@/routes';
import type { Contact, ContactsPageProps } from '../contracts';
import { ContactDetailView } from './contact-detail-view';
import { ContactForm } from './contact-form';
import { ContactsList } from './contacts-list';
import { CustomFieldsManager } from './custom-fields-manager';
import { ImportModal } from './import-modal';

export function ContactsScreen({
  contacts,
  tags,
  customFields,
  canManageCustomFields,
  canWrite,
  filters,
  detailContact: refreshedDetailContact,
  notes,
  customValues,
  contactDeals,
}: ContactsPageProps) {
  // The edit and delete dialogs hold the record they were opened on, so
  // a filter or pagination reload cannot pull it out from under them.
  // The detail sheet stays open across saves, so it follows the server
  // copy by id instead.
  const form = useDialog<Contact>();
  const remove = useDialog<Contact>();
  const detail = useDialog<string>();
  const importer = useDialog();
  const fieldsManager = useDialog();
  const [detailClosed, setDetailClosed] = useState(false);

  useEffect(() => {
    if (refreshedDetailContact && detail.target === null) {
      detail.show(refreshedDetailContact.id);
    }
  }, [detail.show, detail.target, refreshedDetailContact]);

  const detailContact =
    (refreshedDetailContact?.id === detail.target
      ? refreshedDetailContact
      : contacts.data.find((contact) => contact.id === detail.target)) ??
    refreshedDetailContact ??
    null;

  function handleDetailOpenChange(open: boolean): void {
    detail.setOpen(open);
    setDetailClosed(!open);
  }

  function handleDelete() {
    if (!remove.target) {
      return;
    }

    router.delete(destroy(remove.target.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Contacto eliminado.');
        remove.setOpen(false);
      },
      onError: () => toast.error('No se pudo eliminar el contacto.'),
    });
  }

  function handleBulkDelete(selectedContacts: Contact[]) {
    if (selectedContacts.length === 0) {
      return;
    }

    router.delete(bulkDestroy(), {
      data: { ids: selectedContacts.map((contact) => contact.id) },
      preserveScroll: true,
      onSuccess: () =>
        toast.success(`${selectedContacts.length} contactos eliminados.`),
      onError: () => toast.error('No se pudieron eliminar los contactos.'),
    });
  }

  return (
    <>
      <Head title="Contactos" />

      <ContactsList
        contacts={contacts}
        tags={tags}
        filters={filters}
        onAdd={() => form.show()}
        onImport={() => importer.show()}
        onExport={() => window.location.assign(exportContacts.url())}
        onManageCustomFields={() => fieldsManager.show()}
        onOpenDetail={(contact) => {
          setDetailClosed(false);
          detail.show(contact.id);
        }}
        onEdit={(contact) => form.show(contact)}
        onDelete={(contact) => remove.show(contact)}
        onBulkDelete={handleBulkDelete}
      />

      <ContactForm
        key={form.key}
        open={form.open}
        onOpenChange={form.setOpen}
        contact={form.target}
        tags={tags}
      />

      {detailContact ? (
        <ContactDetailView
          key={detail.key}
          open={
            !detailClosed &&
            (detail.open || refreshedDetailContact?.id === detail.target)
          }
          onOpenChange={handleDetailOpenChange}
          contact={detailContact}
          tags={tags}
          customFields={customFields}
          notes={notes}
          customValues={customValues}
          contactDeals={contactDeals}
          canWrite={canWrite}
        />
      ) : null}

      <ImportModal
        key={importer.key}
        open={importer.open}
        onOpenChange={importer.setOpen}
        onImported={() => router.reload({ only: ['contacts', 'tags'] })}
      />

      <CustomFieldsManager
        open={fieldsManager.open}
        onOpenChange={fieldsManager.setOpen}
        fields={customFields}
        canManage={canManageCustomFields}
        onChanged={() => router.reload({ only: ['customFields'] })}
      />

      <Dialog open={remove.open} onOpenChange={remove.setOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Eliminar contacto</DialogTitle>
            <DialogDescription>
              ¿Eliminar a {remove.target?.name || remove.target?.phone}? Esta
              acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => remove.setOpen(false)}>
              Cancelar
            </Button>
            <Button
              data-testid="contacts-delete-confirm"
              variant="destructive"
              onClick={handleDelete}
            >
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

ContactsScreen.layout = {
  breadcrumbs: [
    {
      title: 'Contactos',
      href: contacts(),
    },
  ],
};
