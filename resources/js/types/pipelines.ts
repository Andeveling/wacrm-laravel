import type { Contact } from './contacts';

export interface Pipeline {
  id: string;
  name: string;
  created_at: string;
}

export interface PipelineStage {
  id: string;
  pipeline_id: string;
  name: string;
  position: number;
  color: string;
}

export type DealStatus = 'open' | 'won' | 'lost';

export interface Deal {
  id: string;
  pipeline_id: string;
  stage_id: string;
  contact_id: string | null;
  title: string;
  value: number;
  currency?: string;
  notes?: string;
  expected_close_date?: string;
  status?: DealStatus;
  created_at: string;
  updated_at?: string;
  contact?: Contact;
}
