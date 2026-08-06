import { describe, expect, it } from 'vitest';
import type { Contact } from '@/types';
import {
  deriveContactsList,
  toggleContactSelection,
  togglePageSelection,
} from './model';

const contacts: Contact[] = [
  {
    id: 'contact-1',
    name: 'Ana Pérez',
    phone: '+57 300 111 2222',
    email: 'ana@example.com',
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    tags: [{ id: 'tag-vip', name: 'VIP', color: '#f59e0b' }],
  },
  {
    id: 'contact-2',
    name: 'Bruno Díaz',
    phone: '+57 300 333 4444',
    email: 'bruno@example.com',
    created_at: '2026-01-02T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    tags: [{ id: 'tag-lead', name: 'Prospecto', color: '#3b82f6' }],
  },
  {
    id: 'contact-3',
    name: 'Carla Gómez',
    phone: '+57 300 555 6666',
    email: 'carla@example.com',
    created_at: '2026-01-03T00:00:00Z',
    updated_at: '2026-01-03T00:00:00Z',
  },
];

describe('deriveContactsList', () => {
  it('searches names, phones, and emails without changing the source order', () => {
    const result = deriveContactsList(contacts, '  BRUNO  ', [], 0, 10);

    expect(result.pageRows.map((contact) => contact.id)).toEqual(['contact-2']);
    expect(result.totalCount).toBe(1);
    expect(result.hasActiveFilters).toBe(true);
  });

  it('matches contacts with any selected tag', () => {
    const result = deriveContactsList(
      contacts,
      '',
      ['tag-lead', 'tag-vip'],
      0,
      10,
    );

    expect(result.pageRows.map((contact) => contact.id)).toEqual([
      'contact-1',
      'contact-2',
    ]);
  });

  it('returns the requested page and total page count', () => {
    const result = deriveContactsList(contacts, '', [], 1, 2);

    expect(result.pageRows.map((contact) => contact.id)).toEqual(['contact-3']);
    expect(result.totalCount).toBe(3);
    expect(result.totalPages).toBe(2);
  });
});

describe('contact selection', () => {
  it('toggles one contact without mutating the previous selection', () => {
    const previous = new Set(['contact-1']);
    const next = toggleContactSelection(previous, 'contact-2');

    expect([...previous]).toEqual(['contact-1']);
    expect([...next]).toEqual(['contact-1', 'contact-2']);
  });

  it('selects and deselects the visible page without losing other selections', () => {
    const pageRows = contacts.slice(0, 2);
    const previous = new Set(['contact-3']);
    const selected = togglePageSelection(previous, pageRows);
    const cleared = togglePageSelection(selected, pageRows);

    expect([...selected]).toEqual(['contact-3', 'contact-1', 'contact-2']);
    expect([...cleared]).toEqual(['contact-3']);
  });
});
