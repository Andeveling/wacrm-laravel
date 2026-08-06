export interface Contact {
  id: string;
  phone: string;
  name?: string;
  email?: string;
  company?: string;
  avatar_url?: string;
  created_at: string;
  updated_at: string;
  tags?: Tag[];
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

export interface ContactCustomValue {
  id: string;
  contact_id: string;
  custom_field_id: string;
  value?: string | null;
  custom_field?: CustomField;
}

export interface ContactNote {
  id: string;
  contact_id: string;
  note_text: string;
  created_at: string;
}

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
