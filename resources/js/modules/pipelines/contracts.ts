export interface DealContact {
  id: string;
  phone: string;
  name: string | null;
}

export interface DealAssignee {
  id: number;
  name: string;
}

export interface Pipeline {
  id: string;
  name: string;
  created_at: string | null;
  stages: PipelineStage[];
}

export interface PipelineStage {
  id: string;
  pipeline_id: string;
  name: string;
  position: number;
  color: string;
  deals: Deal[];
}

export type DealStatus = 'open' | 'won' | 'lost';

export interface Deal {
  id: string;
  pipeline_id: string;
  stage_id: string;
  contact_id: string | null;
  title: string;
  value: number | string;
  currency?: string;
  notes?: string;
  expected_close_date?: string;
  status?: DealStatus;
  created_at: string;
  updated_at?: string;
  contact: DealContact | null;
  assignee: DealAssignee | null;
}

export interface PipelinesPageProps {
  pipelines: Pipeline[];
  contacts: DealContact[];
}
