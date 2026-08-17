import { describe, expect, it } from 'vitest';
import {
  buildContactsFilterQuery,
  toggleContactSelection,
  togglePageSelection,
} from './model';
import type { Contact } from './types';

const contacts: Contact[] = [
  {
    id: 'contact-1',
    name: 'Ana Pérez',
    phone: '+57 300 111 2222',
    email: 'ana@example.com',
    company: null,
    avatar_url: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    tags: [{ id: 'tag-vip', name: 'VIP', color: '#f59e0b' }],
  },
  {
    id: 'contact-2',
    name: 'Bruno Díaz',
    phone: '+57 300 333 4444',
    email: 'bruno@example.com',
    company: null,
    avatar_url: null,
    created_at: '2026-01-02T00:00:00Z',
    updated_at: '2026-01-02T00:00:00Z',
    tags: [{ id: 'tag-lead', name: 'Prospecto', color: '#3b82f6' }],
  },
  {
    id: 'contact-3',
    name: 'Carla Gómez',
    phone: '+57 300 555 6666',
    email: 'carla@example.com',
    company: null,
    avatar_url: null,
    created_at: '2026-01-03T00:00:00Z',
    updated_at: '2026-01-03T00:00:00Z',
    tags: [],
  },
];

describe('buildContactsFilterQuery', () => {
  it('omits empty search and tag filters', () => {
    expect(buildContactsFilterQuery('  ', [])).toEqual({});
  });

  it('trims the search term', () => {
    expect(buildContactsFilterQuery('  Bruno  ', [])).toEqual({
      search: 'Bruno',
    });
  });

  it('includes selected tag ids', () => {
    expect(buildContactsFilterQuery('', ['tag-vip', 'tag-lead'])).toEqual({
      tags: ['tag-vip', 'tag-lead'],
    });
  });

  it('combines search and tag filters', () => {
    expect(buildContactsFilterQuery('Bruno', ['tag-lead'])).toEqual({
      search: 'Bruno',
      tags: ['tag-lead'],
    });
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
