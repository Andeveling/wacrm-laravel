import type { Contact, ContactFormValues, Tag } from './contracts';

export function buildContact(
  values: ContactFormValues,
  existing: Contact | null,
  tags: readonly Tag[],
  updatedAt: string,
  generatedId: string,
): Contact {
  const name = values.name.trim();
  const phone = values.phone.trim();
  const email = values.email.trim();
  const company = values.company.trim();

  return {
    id: existing?.id ?? generatedId,
    name: name || null,
    phone,
    email: email || null,
    company: company || null,
    avatar_url: existing?.avatar_url ?? null,
    created_at: existing?.created_at ?? updatedAt,
    updated_at: updatedAt,
    tags: tags.filter((tag) => values.tagIds.includes(tag.id)),
  };
}

export interface ContactsFilterQuery {
  [key: string]: string | string[] | undefined;
  search?: string;
  tags?: string[];
}

export function buildContactsFilterQuery(
  search: string,
  selectedTagIds: readonly string[],
): ContactsFilterQuery {
  const query: ContactsFilterQuery = {};
  const term = search.trim();

  if (term) {
    query.search = term;
  }

  if (selectedTagIds.length > 0) {
    query.tags = [...selectedTagIds];
  }

  return query;
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
