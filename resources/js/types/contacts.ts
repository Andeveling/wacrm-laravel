export interface Contact {
  id: string;
  phone: string;
  name?: string;
  email?: string;
  company?: string;
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
  created_at: string;
}
