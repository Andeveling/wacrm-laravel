import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
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
  notes,
  customValues,
  contactDeals,
}: ContactsPageProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editContact, setEditContact] = useState<Contact | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailContactId, setDetailContactId] = useState<string | null>(null);
  const [importOpen, setImportOpen] = useState(false);
  const [customFieldsOpen, setCustomFieldsOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Contact | null>(null);

  const detailContact = contacts.data.find(
    (contact) => contact.id === detailContactId,
  );

  function openAddForm() {
    setEditContact(null);
    setFormOpen(true);
  }

  function openEditForm(contact: Contact) {
    setEditContact(contact);
    setFormOpen(true);
  }

  function openDetail(contact: Contact) {
    setDetailContactId(contact.id);
    setDetailOpen(true);
  }

  function handleDelete() {
    if (!deleteTarget) return;

    router.delete(destroy(deleteTarget.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Contacto eliminado.');
        setDeleteTarget(null);
      },
      onError: () => toast.error('No se pudo eliminar el contacto.'),
    });
  }

  function handleBulkDelete(selectedContacts: Contact[]) {
    if (selectedContacts.length === 0) return;

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
        onAdd={openAddForm}
        onImport={() => setImportOpen(true)}
        onExport={() => window.location.assign(exportContacts.url())}
        onManageCustomFields={() => setCustomFieldsOpen(true)}
        onOpenDetail={openDetail}
        onEdit={openEditForm}
        onDelete={setDeleteTarget}
        onBulkDelete={handleBulkDelete}
      />

      <ContactForm
        open={formOpen}
        onOpenChange={setFormOpen}
        contact={editContact}
        tags={tags}
      />

      {detailContact && (
        <ContactDetailView
          key={detailContact.id}
          open={detailOpen}
          onOpenChange={setDetailOpen}
          contact={detailContact}
          tags={tags}
          customFields={customFields}
          notes={notes}
          customValues={customValues}
          contactDeals={contactDeals}
          canWrite={canWrite}
        />
      )}

      <ImportModal
        open={importOpen}
        onOpenChange={setImportOpen}
        onImported={() => router.reload({ only: ['contacts', 'tags'] })}
      />

      <CustomFieldsManager
        open={customFieldsOpen}
        onOpenChange={setCustomFieldsOpen}
        fields={customFields}
        canManage={canManageCustomFields}
        onChanged={() => router.reload({ only: ['customFields'] })}
      />

      <Dialog
        open={!!deleteTarget}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Eliminar contacto</DialogTitle>
            <DialogDescription>
              ¿Eliminar a {deleteTarget?.name || deleteTarget?.phone}? Esta
              acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteTarget(null)}>
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
