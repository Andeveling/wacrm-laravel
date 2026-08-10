import type { Paginated } from '@/types/pagination';

/**
 * Mirrors `App\Domain\Contacts\Support\ContactProjection` — the single
 * definition of a Contact's public shape on the backend. Every key is
 * always present; the nullable ones arrive as `null`, not missing.
 */
export interface Contact {
  id: string;
  phone: string;
  name: string | null;
  email: string | null;
  company: string | null;
  avatar_url: string | null;
  created_at: string | null;
  updated_at: string | null;
  tags: Tag[];
}

export interface Tag {
  id: string;
  name: string;
  color: string;
}

export interface ContactTag {
  id: string;
  contact_id: string;
  tag_id: string;
}

export interface CustomField {
  id: string;
  field_name: string;
  field_type: string;
  field_options?: Record<string, unknown> | null;
  created_at: string;
}

export interface ContactNote {
  id: string;
  contact_id: string;
  note_text: string;
  created_at: string | null;
  user: { id: number; name: string | null };
}

/** Mirrors `App\Models\Enums\DealStatus`. */
export type DealStatus = 'open' | 'won' | 'lost';

export interface ContactDeal {
  id: string;
  title: string;
  value: string;
  currency: string | null;
  status: DealStatus | null;
  stage: { id: string; name: string; color: string } | null;
}

export interface ContactsFilters {
  search: string;
  tags: string[];
}

export interface ContactsPageProps {
  contacts: Paginated<Contact>;
  tags: Tag[];
  customFields: CustomField[];
  canManageCustomFields: boolean;
  canWrite: boolean;
  filters: ContactsFilters;
  notes?: ContactNote[];
  customValues?: Record<string, string | null>;
  contactDeals?: ContactDeal[];
}

export interface ContactFieldsPageProps {
  tags: Tag[];
  customFields: CustomField[];
  canManage: boolean;
}

export interface ContactFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact?: Contact | null;
  tags: Tag[];
}
