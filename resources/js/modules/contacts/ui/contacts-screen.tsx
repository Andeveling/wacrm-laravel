import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ContactDetailView } from '@/components/contacts/contact-detail-view';
import { ContactForm } from '@/components/contacts/contact-form';
import { CustomFieldsManager } from '@/components/contacts/custom-fields-manager';
import { ImportModal } from '@/components/contacts/import-modal';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { MOCK_TAGS, mockContacts } from '@/lib/contacts/mock';
import type { Contact } from '@/types';
import type { ContactsPageProps } from '../contracts';
import { ContactsList } from './contacts-list';

export function ContactsScreen({
  contacts: initialContacts,
  tags = MOCK_TAGS,
}: ContactsPageProps) {
  const [contacts, setContacts] = useState<Contact[]>(
    () => initialContacts ?? mockContacts(),
  );
  const [formOpen, setFormOpen] = useState(false);
  const [editContact, setEditContact] = useState<Contact | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailContact, setDetailContact] = useState<Contact | null>(null);
  const [importOpen, setImportOpen] = useState(false);
  const [customFieldsOpen, setCustomFieldsOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Contact | null>(null);

  function openAddForm() {
    setEditContact(null);
    setFormOpen(true);
  }

  function openEditForm(contact: Contact) {
    setEditContact(contact);
    setFormOpen(true);
  }

  function openDetail(contact: Contact) {
    setDetailContact(contact);
    setDetailOpen(true);
  }

  function upsertContact(contact: Contact) {
    setContacts((previous) => {
      const exists = previous.some((item) => item.id === contact.id);
      return exists
        ? previous.map((item) => (item.id === contact.id ? contact : item))
        : [contact, ...previous];
    });
    setDetailContact((previous) =>
      previous && previous.id === contact.id ? contact : previous,
    );
  }

  function handleDelete() {
    if (!deleteTarget) return;

    setContacts((previous) =>
      previous.filter((contact) => contact.id !== deleteTarget.id),
    );
    toast.success('Contacto eliminado.');
    setDeleteTarget(null);
  }

  function handleBulkDelete(selectedContacts: Contact[]) {
    const selectedIds = new Set(selectedContacts.map((contact) => contact.id));

    setContacts((previous) =>
      previous.filter((contact) => !selectedIds.has(contact.id)),
    );
    toast.success(`${selectedContacts.length} contactos eliminados.`);
  }

  return (
    <>
      <Head title="Contactos" />

      <ContactsList
        contacts={contacts}
        tags={tags}
        onAdd={openAddForm}
        onImport={() => setImportOpen(true)}
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
        onSaved={upsertContact}
      />

      <ContactDetailView
        open={detailOpen}
        onOpenChange={setDetailOpen}
        contact={detailContact}
        onUpdated={upsertContact}
      />

      <ImportModal open={importOpen} onOpenChange={setImportOpen} />

      <CustomFieldsManager
        open={customFieldsOpen}
        onOpenChange={setCustomFieldsOpen}
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
            <Button variant="destructive" onClick={handleDelete}>
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
