import type { Contact, CustomField, Tag } from '@/types';

export const MOCK_TAGS: Tag[] = [
  { id: 'tag-vip', name: 'VIP', color: '#f59e0b' },
  { id: 'tag-lead', name: 'Prospecto', color: '#3b82f6' },
  { id: 'tag-client', name: 'Cliente', color: '#10b981' },
  { id: 'tag-support', name: 'Soporte', color: '#7c3aed' },
];

export function mockCustomFields(): CustomField[] {
  return [
    {
      id: 'field-source',
      field_name: 'Fuente',
      field_type: 'text',
      created_at: new Date(Date.now() - 3 * 86_400_000).toISOString(),
    },
    {
      id: 'field-industry',
      field_name: 'Industria',
      field_type: 'text',
      created_at: new Date(Date.now() - 2 * 86_400_000).toISOString(),
    },
    {
      id: 'field-budget',
      field_name: 'Presupuesto',
      field_type: 'text',
      created_at: new Date(Date.now() - 86_400_000).toISOString(),
    },
  ];
}

const NAMES = [
  'Laura Gómez',
  'Carlos Ruiz',
  'María Fernanda López',
  'Andrés Torres',
  'Camila Rodríguez',
  'Santiago Martínez',
  'Valentina Herrera',
  'Juan Pablo Díaz',
];

export function mockContacts(count = 24): Contact[] {
  return Array.from({ length: count }, (_, i) => {
    const name = NAMES[i % NAMES.length];
    const tags = MOCK_TAGS.filter((_, ti) => (i + ti) % 3 === 0);
    return {
      id: `contact-${i}`,
      name,
      phone: `+57 3${(100000000 + i * 137).toString().slice(0, 9)}`,
      email: `${name.toLowerCase().split(' ')[0]}@example.com`,
      company: i % 4 === 0 ? 'Acme SAS' : undefined,
      created_at: new Date(Date.now() - i * 86_400_000).toISOString(),
      updated_at: new Date(Date.now() - i * 86_400_000).toISOString(),
      tags,
    };
  });
}
