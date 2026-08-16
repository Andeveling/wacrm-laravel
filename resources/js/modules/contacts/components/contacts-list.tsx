// biome-ignore lint/style/noExcessiveLinesPerFile: This legacy list keeps its table markup in one module.
import { router } from '@inertiajs/react';
import {
  Download,
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
import { useEffect, useRef, useState } from 'react';
import { Pagination } from '@/components/pagination';
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
import { contacts as contactsRoute } from '@/routes';
import type { Paginated } from '@/types/pagination';
import {
  buildContactsFilterQuery,
  toggleContactSelection,
  togglePageSelection,
} from '../model';
import type { Contact, ContactsFilters, Tag } from '../types';

type ContactsListProps = {
  contacts: Paginated<Contact>;
  tags: Tag[];
  filters: ContactsFilters;
  onAdd: () => void;
  onImport: () => void;
  onExport: () => void;
  onManageCustomFields: () => void;
  onOpenDetail: (contact: Contact) => void;
  onEdit: (contact: Contact) => void;
  onDelete: (contact: Contact) => void;
  onBulkDelete: (contactIds: string[]) => void;
};

export function ContactsList({
  contacts,
  tags,
  filters,
  onAdd,
  onImport,
  onExport,
  onManageCustomFields,
  onOpenDetail,
  onEdit,
  onDelete,
  onBulkDelete,
}: ContactsListProps) {
  const [search, setSearch] = useState(filters.search);
  const [selectedTagIds, setSelectedTagIds] = useState<string[]>(filters.tags);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const searchDebounce = useRef<ReturnType<typeof setTimeout>>(undefined);

  const rows = contacts.data;
  const hasActiveFilters = Boolean(search.trim()) || selectedTagIds.length > 0;
  const allOnPageSelected =
    rows.length > 0 && rows.every((contact) => selected.has(contact.id));
  const someOnPageSelected = rows.some((contact) => selected.has(contact.id));

  // The only reason for an effect here: the pending timer is an external
  // resource that must not outlive the component, or it navigates back to
  // contacts after the user has already left the page.
  useEffect(() => () => clearTimeout(searchDebounce.current), []);

  function applyFilters(nextSearch: string, nextTagIds: string[]) {
    clearTimeout(searchDebounce.current);

    searchDebounce.current = setTimeout(() => {
      router.get(
        contactsRoute.url(),
        {
          ...buildContactsFilterQuery(nextSearch, nextTagIds),
          per_page: contacts.per_page,
        },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
          only: ['contacts', 'filters'],
        },
      );
    }, 300);
  }

  function changeSearch(term: string) {
    setSearch(term);
    applyFilters(term, selectedTagIds);
  }

  function toggleTagFilter(tagId: string) {
    const nextTagIds = selectedTagIds.includes(tagId)
      ? selectedTagIds.filter((id) => id !== tagId)
      : [...selectedTagIds, tagId];

    setSelectedTagIds(nextTagIds);
    applyFilters(search, nextTagIds);
  }

  function clearTagFilters() {
    setSelectedTagIds([]);
    applyFilters(search, []);
  }

  function handleBulkDelete() {
    onBulkDelete([...selected]);
    setSelected(new Set());
    setBulkDeleteOpen(false);
  }

  return (
    <>
      <div className="space-y-6">
        <ContactsHeader
          contacts={contacts}
          onAdd={onAdd}
          onImport={onImport}
          onExport={onExport}
          onManageCustomFields={onManageCustomFields}
        />
        <ContactsFiltersSection
          search={search}
          selectedTagIds={selectedTagIds}
          tags={tags}
          onChangeSearch={changeSearch}
          onToggleTagFilter={toggleTagFilter}
          onClearTagFilters={clearTagFilters}
        />
        {selected.size > 0 ? (
          <SelectionToolbar
            selectedCount={selected.size}
            onClear={() => setSelected(new Set())}
            onDelete={() => setBulkDeleteOpen(true)}
          />
        ) : null}
        <ContactsTable
          rows={rows}
          hasActiveFilters={hasActiveFilters}
          selected={selected}
          allOnPageSelected={allOnPageSelected}
          someOnPageSelected={someOnPageSelected}
          onAdd={onAdd}
          onOpenDetail={onOpenDetail}
          onEdit={onEdit}
          onDelete={onDelete}
          onTogglePage={() =>
            setSelected((previous) => togglePageSelection(previous, rows))
          }
          onToggleContact={(contactId) =>
            setSelected((previous) =>
              toggleContactSelection(previous, contactId),
            )
          }
        />
        <Pagination
          meta={contacts}
          only={['contacts', 'filters']}
          previousTestId="contacts-previous-page"
          nextTestId="contacts-next-page"
        />
      </div>
      <BulkDeleteDialog
        open={bulkDeleteOpen}
        selectedCount={selected.size}
        onOpenChange={setBulkDeleteOpen}
        onConfirm={handleBulkDelete}
      />
    </>
  );
}

type ContactsHeaderProps = Pick<
  ContactsListProps,
  'contacts' | 'onAdd' | 'onImport' | 'onExport' | 'onManageCustomFields'
>;

function ContactsHeader({
  contacts,
  onAdd,
  onImport,
  onExport,
  onManageCustomFields,
}: ContactsHeaderProps) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Contactos</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          {contacts.total > 0
            ? `${contacts.total} contactos`
            : 'Sin contactos todavía'}
        </p>
      </div>
      <div className="flex items-center gap-2">
        <Button variant="outline" onClick={onManageCustomFields}>
          <SlidersHorizontal className="size-4" />
          Campos personalizados
        </Button>
        <Button variant="outline" onClick={onImport}>
          <Upload className="size-4" />
          Importar
        </Button>
        <Button variant="outline" onClick={onExport}>
          <Download className="size-4" />
          Exportar
        </Button>
        <Button data-testid="contacts-add" onClick={onAdd}>
          <Plus className="size-4" />
          Agregar contacto
        </Button>
      </div>
    </div>
  );
}

type ContactsFiltersSectionProps = {
  search: string;
  selectedTagIds: string[];
  tags: Tag[];
  onChangeSearch: (term: string) => void;
  onToggleTagFilter: (tagId: string) => void;
  onClearTagFilters: () => void;
};

function ContactsFiltersSection({
  search,
  selectedTagIds,
  tags,
  onChangeSearch,
  onToggleTagFilter,
  onClearTagFilters,
}: ContactsFiltersSectionProps) {
  return (
    <div className="space-y-2">
      <div className="flex flex-col gap-2 sm:flex-row">
        <Input
          value={search}
          onChange={(event) => onChangeSearch(event.target.value)}
          placeholder="Buscar por nombre, teléfono o correo…"
          className="max-w-sm"
        />
        <TagFilterPopover
          tags={tags}
          selectedTagIds={selectedTagIds}
          onToggleTagFilter={onToggleTagFilter}
          onClearTagFilters={onClearTagFilters}
        />
      </div>
      {selectedTagIds.length > 0 ? (
        <ActiveTagList
          tags={tags}
          selectedTagIds={selectedTagIds}
          onToggleTagFilter={onToggleTagFilter}
        />
      ) : null}
    </div>
  );
}

type TagFilterPopoverProps = {
  tags: Tag[];
  selectedTagIds: string[];
  onToggleTagFilter: (tagId: string) => void;
  onClearTagFilters: () => void;
};

function TagFilterPopover({
  tags,
  selectedTagIds,
  onToggleTagFilter,
  onClearTagFilters,
}: TagFilterPopoverProps) {
  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button
            data-testid="contacts-tag-filter"
            variant="outline"
            className="shrink-0"
          />
        }
      >
        <Filter className="size-4" />
        Filtrar por etiqueta
        {selectedTagIds.length > 0 ? (
          <span className="ml-1 inline-flex items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
            {selectedTagIds.length}
          </span>
        ) : null}
      </PopoverTrigger>
      <PopoverContent align="start" className="w-64 p-0">
        <div className="flex items-center justify-between border-b px-3 py-2">
          <span className="text-sm font-medium">Etiquetas</span>
          {selectedTagIds.length > 0 ? (
            <button
              type="button"
              onClick={onClearTagFilters}
              className="text-xs text-muted-foreground hover:text-foreground"
            >
              Limpiar
            </button>
          ) : null}
        </div>
        <div className="max-h-64 overflow-y-auto py-1">
          {tags.map((tag) => (
            <label
              key={tag.id}
              data-testid={`contacts-tag-${tag.name.toLowerCase().replaceAll(' ', '-')}`}
              htmlFor={`tag-filter-${tag.id}`}
              className="flex cursor-pointer items-center gap-2.5 px-3 py-1.5 hover:bg-muted/50"
            >
              <Checkbox
                id={`tag-filter-${tag.id}`}
                checked={selectedTagIds.includes(tag.id)}
                onCheckedChange={() => onToggleTagFilter(tag.id)}
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
  );
}

type ActiveTagListProps = Pick<
  TagFilterPopoverProps,
  'tags' | 'selectedTagIds' | 'onToggleTagFilter'
>;

function ActiveTagList({
  tags,
  selectedTagIds,
  onToggleTagFilter,
}: ActiveTagListProps) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      {selectedTagIds.map((id) => {
        const tag = tags.find((candidate) => candidate.id === id);
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
              onClick={() => onToggleTagFilter(id)}
              className="hover:opacity-70"
            >
              <X className="size-3" />
            </button>
          </span>
        );
      })}
    </div>
  );
}

type SelectionToolbarProps = {
  selectedCount: number;
  onClear: () => void;
  onDelete: () => void;
};

function SelectionToolbar({
  selectedCount,
  onClear,
  onDelete,
}: SelectionToolbarProps) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border bg-muted/40 px-4 py-2">
      <p className="text-sm text-foreground">{selectedCount} seleccionados</p>
      <div className="flex items-center gap-2">
        <Button variant="ghost" size="sm" onClick={onClear}>
          Deseleccionar
        </Button>
        <Button
          data-testid="contacts-bulk-delete"
          variant="destructive"
          size="sm"
          onClick={onDelete}
        >
          <Trash2 className="size-4" />
          Eliminar seleccionados
        </Button>
      </div>
    </div>
  );
}

type ContactsTableProps = {
  rows: Contact[];
  hasActiveFilters: boolean;
  selected: Set<string>;
  allOnPageSelected: boolean;
  someOnPageSelected: boolean;
  onAdd: () => void;
  onOpenDetail: (contact: Contact) => void;
  onEdit: (contact: Contact) => void;
  onDelete: (contact: Contact) => void;
  onTogglePage: () => void;
  onToggleContact: (contactId: string) => void;
};

function ContactsTable({
  rows,
  hasActiveFilters,
  selected,
  allOnPageSelected,
  someOnPageSelected,
  onAdd,
  onOpenDetail,
  onEdit,
  onDelete,
  onTogglePage,
  onToggleContact,
}: ContactsTableProps) {
  return (
    <div className="overflow-hidden rounded-lg border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-10">
              <Checkbox
                checked={allOnPageSelected}
                indeterminate={someOnPageSelected && !allOnPageSelected}
                onCheckedChange={onTogglePage}
                disabled={rows.length === 0}
              />
            </TableHead>
            <TableHead>Nombre</TableHead>
            <TableHead>Teléfono</TableHead>
            <TableHead className="hidden md:table-cell">Correo</TableHead>
            <TableHead className="hidden lg:table-cell">Empresa</TableHead>
            <TableHead className="hidden md:table-cell">Etiquetas</TableHead>
            <TableHead className="hidden lg:table-cell">Creado</TableHead>
            <TableHead className="w-12" />
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.length === 0 ? (
            <EmptyContactsRow
              hasActiveFilters={hasActiveFilters}
              onAdd={onAdd}
            />
          ) : (
            rows.map((contact, rowIndex) => (
              <ContactRow
                key={contact.id}
                contact={contact}
                rowIndex={rowIndex}
                isSelected={selected.has(contact.id)}
                onOpenDetail={onOpenDetail}
                onEdit={onEdit}
                onDelete={onDelete}
                onToggle={() => onToggleContact(contact.id)}
              />
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}

type EmptyContactsRowProps = {
  hasActiveFilters: boolean;
  onAdd: () => void;
};

function EmptyContactsRow({ hasActiveFilters, onAdd }: EmptyContactsRowProps) {
  return (
    <TableRow>
      <TableCell colSpan={8} className="py-12 text-center">
        <div className="flex flex-col items-center gap-2">
          <Users className="size-8 text-muted-foreground" />
          <p className="text-sm text-muted-foreground">
            {hasActiveFilters
              ? 'Ningún contacto coincide con el filtro.'
              : 'Todavía no hay contactos.'}
          </p>
          {hasActiveFilters ? null : (
            <Button
              variant="outline"
              size="sm"
              onClick={onAdd}
              className="mt-2"
            >
              <Plus className="size-3.5" />
              Agregar el primero
            </Button>
          )}
        </div>
      </TableCell>
    </TableRow>
  );
}

type ContactRowProps = {
  contact: Contact;
  rowIndex: number;
  isSelected: boolean;
  onOpenDetail: (contact: Contact) => void;
  onEdit: (contact: Contact) => void;
  onDelete: (contact: Contact) => void;
  onToggle: () => void;
};

function ContactRow({
  contact,
  rowIndex,
  isSelected,
  onOpenDetail,
  onEdit,
  onDelete,
  onToggle,
}: ContactRowProps) {
  return (
    <TableRow
      data-testid={`contact-row-${rowIndex}`}
      className="cursor-pointer"
      onClick={() => onOpenDetail(contact)}
    >
      <TableCell onClick={(event) => event.stopPropagation()}>
        <Checkbox checked={isSelected} onCheckedChange={onToggle} />
      </TableCell>
      <TableCell className="font-medium text-foreground">
        {contact.name || (
          <span className="text-muted-foreground italic">Sin nombre</span>
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
          {contact.tags.length > 0 ? (
            contact.tags.slice(0, 3).map((tag) => (
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
            <span className="text-xs text-muted-foreground">—</span>
          )}
        </div>
      </TableCell>
      <TableCell className="hidden text-xs text-muted-foreground lg:table-cell">
        {contact.created_at
          ? new Date(contact.created_at).toLocaleDateString('es-CO', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
            })
          : '—'}
      </TableCell>
      <ContactActions
        contact={contact}
        rowIndex={rowIndex}
        onEdit={onEdit}
        onDelete={onDelete}
      />
    </TableRow>
  );
}

type ContactActionsProps = Pick<
  ContactRowProps,
  'contact' | 'rowIndex' | 'onEdit' | 'onDelete'
>;

function ContactActions({
  contact,
  rowIndex,
  onEdit,
  onDelete,
}: ContactActionsProps) {
  return (
    <TableCell>
      <DropdownMenu>
        <DropdownMenuTrigger
          render={
            <Button
              data-testid={`contact-actions-row-${rowIndex}`}
              variant="ghost"
              size="icon"
              onClick={(event) => event.stopPropagation()}
            />
          }
        >
          <MoreHorizontal className="size-4" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem
            data-testid={`contact-edit-row-${rowIndex}`}
            onClick={(event) => {
              event.stopPropagation();
              onEdit(contact);
            }}
          >
            <Pencil className="size-4" />
            Editar
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            data-testid={`contact-delete-row-${rowIndex}`}
            variant="destructive"
            onClick={(event) => {
              event.stopPropagation();
              onDelete(contact);
            }}
          >
            <Trash2 className="size-4" />
            Eliminar
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </TableCell>
  );
}

type BulkDeleteDialogProps = {
  open: boolean;
  selectedCount: number;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
};

function BulkDeleteDialog({
  open,
  selectedCount,
  onOpenChange,
  onConfirm,
}: BulkDeleteDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>Eliminar contactos</DialogTitle>
          <DialogDescription>
            ¿Eliminar {selectedCount} contactos seleccionados? Esta acción no se
            puede deshacer.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            data-testid="contacts-bulk-delete-confirm"
            variant="destructive"
            onClick={onConfirm}
          >
            Eliminar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
