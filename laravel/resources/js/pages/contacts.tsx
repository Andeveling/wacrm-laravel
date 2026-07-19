import { Head } from '@inertiajs/react';
import {
  ChevronLeft,
  ChevronRight,
  Filter,
  MoreHorizontal,
  Pencil,
  Plus,
  SlidersHorizontal,
  Trash2,
  Upload,
  Users,
  X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ContactDetailView } from '@/components/contacts/contact-detail-view';
import { ContactForm } from '@/components/contacts/contact-form';
import { CustomFieldsManager } from '@/components/contacts/custom-fields-manager';
import { ImportModal } from '@/components/contacts/import-modal';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { MOCK_TAGS, mockContacts } from '@/lib/contacts/mock';
import type { Contact } from '@/types';

const PAGE_SIZE = 10;

export default function ContactsPage() {
  const [contacts, setContacts] = useState<Contact[]>(() => mockContacts());
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const [selectedTagIds, setSelectedTagIds] = useState<string[]>([]);

  const [formOpen, setFormOpen] = useState(false);
  const [editContact, setEditContact] = useState<Contact | null>(null);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailContact, setDetailContact] = useState<Contact | null>(null);
  const [importOpen, setImportOpen] = useState(false);
  const [customFieldsOpen, setCustomFieldsOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Contact | null>(null);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);

  const [selected, setSelected] = useState<Set<string>>(new Set());

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return contacts.filter((c) => {
      const matchesTerm =
        !term ||
        c.name?.toLowerCase().includes(term) ||
        c.phone.includes(term) ||
        c.email?.toLowerCase().includes(term);
      const matchesTags =
        selectedTagIds.length === 0 ||
        (c.tags ?? []).some((tag) => selectedTagIds.includes(tag.id));
      return matchesTerm && matchesTags;
    });
  }, [contacts, search, selectedTagIds]);

  const totalCount = filtered.length;
  const totalPages = Math.max(1, Math.ceil(totalCount / PAGE_SIZE));
  const pageRows = filtered.slice(
    page * PAGE_SIZE,
    page * PAGE_SIZE + PAGE_SIZE,
  );
  const hasActiveFilters =
    search.trim().length > 0 || selectedTagIds.length > 0;

  const allOnPageSelected =
    pageRows.length > 0 && pageRows.every((c) => selected.has(c.id));
  const someOnPageSelected = pageRows.some((c) => selected.has(c.id));

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
    setContacts((prev) => {
      const exists = prev.some((c) => c.id === contact.id);
      return exists
        ? prev.map((c) => (c.id === contact.id ? contact : c))
        : [contact, ...prev];
    });
    setDetailContact((prev) =>
      prev && prev.id === contact.id ? contact : prev,
    );
  }

  function handleDelete() {
    if (!deleteTarget) return;
    setContacts((prev) => prev.filter((c) => c.id !== deleteTarget.id));
    toast.success('Contacto eliminado.');
    setDeleteTarget(null);
  }

  function handleBulkDelete() {
    setContacts((prev) => prev.filter((c) => !selected.has(c.id)));
    toast.success(`${selected.size} contactos eliminados.`);
    setSelected(new Set());
    setBulkDeleteOpen(false);
  }

  function toggleSelectAll() {
    setSelected((prev) => {
      const next = new Set(prev);
      if (allOnPageSelected) {
        pageRows.forEach((c) => {
          next.delete(c.id);
        });
      } else {
        pageRows.forEach((c) => {
          next.add(c.id);
        });
      }
      return next;
    });
  }

  function toggleSelect(id: string) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleTagFilter(tagId: string) {
    setSelectedTagIds((prev) =>
      prev.includes(tagId)
        ? prev.filter((id) => id !== tagId)
        : [...prev, tagId],
    );
    setPage(0);
  }

  function clearTagFilters() {
    setSelectedTagIds([]);
    setPage(0);
  }

  return (
    <>
      <Head title="Contactos" />

      <div className="space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Contactos</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              {totalCount > 0
                ? `${totalCount} contactos`
                : 'Sin contactos todavía'}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setCustomFieldsOpen(true)}>
              <SlidersHorizontal className="size-4" />
              Campos personalizados
            </Button>
            <Button variant="outline" onClick={() => setImportOpen(true)}>
              <Upload className="size-4" />
              Importar
            </Button>
            <Button onClick={openAddForm}>
              <Plus className="size-4" />
              Agregar contacto
            </Button>
          </div>
        </div>

        <div className="space-y-2">
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(0);
              }}
              placeholder="Buscar por nombre, teléfono o correo…"
              className="max-w-sm"
            />

            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline" className="shrink-0">
                  <Filter className="size-4" />
                  Filtrar por etiqueta
                  {selectedTagIds.length > 0 && (
                    <span className="ml-1 inline-flex items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                      {selectedTagIds.length}
                    </span>
                  )}
                </Button>
              </PopoverTrigger>
              <PopoverContent align="start" className="w-64 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2">
                  <span className="text-sm font-medium">Etiquetas</span>
                  {selectedTagIds.length > 0 && (
                    <button
                      type="button"
                      onClick={clearTagFilters}
                      className="text-xs text-muted-foreground hover:text-foreground"
                    >
                      Limpiar
                    </button>
                  )}
                </div>
                <div className="max-h-64 overflow-y-auto py-1">
                  {MOCK_TAGS.map((tag) => (
                    <label
                      key={tag.id}
                      htmlFor={`tag-filter-${tag.id}`}
                      className="flex cursor-pointer items-center gap-2.5 px-3 py-1.5 hover:bg-muted/50"
                    >
                      <Checkbox
                        id={`tag-filter-${tag.id}`}
                        checked={selectedTagIds.includes(tag.id)}
                        onCheckedChange={() => toggleTagFilter(tag.id)}
                      />
                      <span
                        className="size-2.5 shrink-0 rounded-full"
                        style={{ backgroundColor: tag.color }}
                      />
                      <span className="truncate text-sm">{tag.name}</span>
                    </label>
                  ))}
                </div>
              </PopoverContent>
            </Popover>
          </div>

          {selectedTagIds.length > 0 && (
            <div className="flex flex-wrap items-center gap-1.5">
              {selectedTagIds.map((id) => {
                const tag = MOCK_TAGS.find((t) => t.id === id);
                if (!tag) return null;
                return (
                  <span
                    key={id}
                    className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                    style={{
                      backgroundColor: `${tag.color}20`,
                      color: tag.color,
                    }}
                  >
                    {tag.name}
                    <button
                      type="button"
                      onClick={() => toggleTagFilter(id)}
                      className="hover:opacity-70"
                    >
                      <X className="size-3" />
                    </button>
                  </span>
                );
              })}
            </div>
          )}
        </div>

        {selected.size > 0 && (
          <div className="flex items-center justify-between gap-4 rounded-lg border bg-muted/40 px-4 py-2">
            <p className="text-sm text-foreground">
              {selected.size} seleccionados
            </p>
            <div className="flex items-center gap-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setSelected(new Set())}
              >
                Deseleccionar
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => setBulkDeleteOpen(true)}
              >
                <Trash2 className="size-4" />
                Eliminar seleccionados
              </Button>
            </div>
          </div>
        )}

        <div className="overflow-hidden rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">
                  <Checkbox
                    checked={
                      allOnPageSelected
                        ? true
                        : someOnPageSelected
                          ? 'indeterminate'
                          : false
                    }
                    onCheckedChange={toggleSelectAll}
                    disabled={pageRows.length === 0}
                  />
                </TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Teléfono</TableHead>
                <TableHead className="hidden md:table-cell">Correo</TableHead>
                <TableHead className="hidden lg:table-cell">Empresa</TableHead>
                <TableHead className="hidden md:table-cell">
                  Etiquetas
                </TableHead>
                <TableHead className="hidden lg:table-cell">Creado</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {pageRows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-12 text-center">
                    <div className="flex flex-col items-center gap-2">
                      <Users className="size-8 text-muted-foreground" />
                      <p className="text-sm text-muted-foreground">
                        {hasActiveFilters
                          ? 'Ningún contacto coincide con el filtro.'
                          : 'Todavía no hay contactos.'}
                      </p>
                      {!hasActiveFilters && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={openAddForm}
                          className="mt-2"
                        >
                          <Plus className="size-3.5" />
                          Agregar el primero
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ) : (
                pageRows.map((contact) => (
                  <TableRow
                    key={contact.id}
                    className="cursor-pointer"
                    onClick={() => openDetail(contact)}
                  >
                    <TableCell onClick={(e) => e.stopPropagation()}>
                      <Checkbox
                        checked={selected.has(contact.id)}
                        onCheckedChange={() => toggleSelect(contact.id)}
                      />
                    </TableCell>
                    <TableCell className="font-medium text-foreground">
                      {contact.name || (
                        <span className="text-muted-foreground italic">
                          Sin nombre
                        </span>
                      )}
                    </TableCell>
                    <TableCell className="font-mono text-xs text-muted-foreground">
                      {contact.phone}
                    </TableCell>
                    <TableCell className="hidden text-sm text-muted-foreground md:table-cell">
                      {contact.email || '—'}
                    </TableCell>
                    <TableCell className="hidden text-sm text-muted-foreground lg:table-cell">
                      {contact.company || '—'}
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      <div className="flex flex-wrap gap-1">
                        {(contact.tags ?? []).length > 0 ? (
                          contact.tags?.slice(0, 3).map((tag) => (
                            <span
                              key={tag.id}
                              className="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                              style={{
                                backgroundColor: `${tag.color}20`,
                                color: tag.color,
                              }}
                            >
                              {tag.name}
                            </span>
                          ))
                        ) : (
                          <span className="text-xs text-muted-foreground">
                            —
                          </span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="hidden text-xs text-muted-foreground lg:table-cell">
                      {new Date(contact.created_at).toLocaleDateString(
                        'es-CO',
                        { month: 'short', day: 'numeric', year: 'numeric' },
                      )}
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={(e) => e.stopPropagation()}
                          >
                            <MoreHorizontal className="size-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem
                            onClick={(e) => {
                              e.stopPropagation();
                              openEditForm(contact);
                            }}
                          >
                            <Pencil className="size-4" />
                            Editar
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem
                            variant="destructive"
                            onClick={(e) => {
                              e.stopPropagation();
                              setDeleteTarget(contact);
                            }}
                          >
                            <Trash2 className="size-4" />
                            Eliminar
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {totalPages > 1 && (
          <div className="flex items-center justify-between">
            <p className="text-xs text-muted-foreground">
              Mostrando {page * PAGE_SIZE + 1}–
              {Math.min((page + 1) * PAGE_SIZE, totalCount)} de {totalCount}
            </p>
            <div className="flex items-center gap-1">
              <Button
                variant="outline"
                size="icon"
                disabled={page === 0}
                onClick={() => setPage((p) => p - 1)}
              >
                <ChevronLeft className="size-4" />
              </Button>
              <span className="px-2 text-xs text-muted-foreground">
                Página {page + 1} de {totalPages}
              </span>
              <Button
                variant="outline"
                size="icon"
                disabled={page >= totalPages - 1}
                onClick={() => setPage((p) => p + 1)}
              >
                <ChevronRight className="size-4" />
              </Button>
            </div>
          </div>
        )}
      </div>

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

      <Dialog open={bulkDeleteOpen} onOpenChange={setBulkDeleteOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Eliminar contactos</DialogTitle>
            <DialogDescription>
              ¿Eliminar {selected.size} contactos seleccionados? Esta acción no
              se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setBulkDeleteOpen(false)}>
              Cancelar
            </Button>
            <Button variant="destructive" onClick={handleBulkDelete}>
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

ContactsPage.layout = {
  breadcrumbs: [
    {
      title: 'Contactos',
      href: '/contacts',
    },
  ],
};
