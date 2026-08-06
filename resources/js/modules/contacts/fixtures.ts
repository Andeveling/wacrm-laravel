import type { CustomField, Tag } from './contracts';

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
