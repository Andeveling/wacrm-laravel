import type { Contact } from '@/types';

export interface ContactsListResult {
  pageRows: Contact[];
  totalCount: number;
  totalPages: number;
  hasActiveFilters: boolean;
}

export function deriveContactsList(
  contacts: readonly Contact[],
  search: string,
  selectedTagIds: readonly string[],
  page: number,
  pageSize: number,
): ContactsListResult {
  const term = search.trim().toLowerCase();
  const filtered = contacts.filter((contact) => {
    const matchesTerm =
      !term ||
      contact.name?.toLowerCase().includes(term) ||
      contact.phone.includes(term) ||
      contact.email?.toLowerCase().includes(term);
    const matchesTags =
      selectedTagIds.length === 0 ||
      (contact.tags ?? []).some((tag) => selectedTagIds.includes(tag.id));

    return matchesTerm && matchesTags;
  });
  const safePageSize = Math.max(1, pageSize);
  const safePage = Math.max(0, page);
  const totalPages = Math.max(1, Math.ceil(filtered.length / safePageSize));
  const pageStart = safePage * safePageSize;

  return {
    pageRows: filtered.slice(pageStart, pageStart + safePageSize),
    totalCount: filtered.length,
    totalPages,
    hasActiveFilters: Boolean(term) || selectedTagIds.length > 0,
  };
}

export function toggleContactSelection(
  selected: ReadonlySet<string>,
  contactId: string,
): Set<string> {
  const next = new Set(selected);

  if (next.has(contactId)) {
    next.delete(contactId);
  } else {
    next.add(contactId);
  }

  return next;
}

export function togglePageSelection(
  selected: ReadonlySet<string>,
  pageRows: readonly Contact[],
): Set<string> {
  const next = new Set(selected);
  const allOnPageSelected =
    pageRows.length > 0 && pageRows.every((contact) => next.has(contact.id));

  for (const contact of pageRows) {
    if (allOnPageSelected) {
      next.delete(contact.id);
    } else {
      next.add(contact.id);
    }
  }

  return next;
}
