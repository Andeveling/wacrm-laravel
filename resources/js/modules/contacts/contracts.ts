import type { Contact, CustomField, Tag } from '@/types';

export interface ContactsPageProps {
  contacts: Contact[];
  tags: Tag[];
  customFields: CustomField[];
  canManageCustomFields: boolean;
}

export interface ContactFormValues {
  name: string;
  phone: string;
  email: string;
  company: string;
  tagIds: string[];
}

export interface ContactFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  contact?: Contact | null;
  tags: Tag[];
  onUpdated: (contact: Contact) => void;
}
