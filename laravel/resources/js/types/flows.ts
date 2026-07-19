export type FlowStatus = 'draft' | 'active' | 'archived';
export type FlowTriggerType = 'keyword' | 'first_inbound_message' | 'manual';

export interface Flow {
  id: string;
  name: string;
  description: string | null;
  status: FlowStatus;
  trigger_type: FlowTriggerType;
  trigger_config: { keywords?: string[] } | Record<string, unknown>;
  execution_count: number;
  last_executed_at: string | null;
  created_at: string;
  updated_at: string;
}

export type FlowRunStatus =
  | 'active'
  | 'completed'
  | 'handed_off'
  | 'timed_out'
  | 'paused_by_agent'
  | 'failed';

export interface FlowRun {
  id: string;
  status: FlowRunStatus;
  current_node_key: string | null;
  started_at: string;
  ended_at: string | null;
  reprompt_count: number;
  contact: { id: string; name: string | null; phone: string } | null;
}

export interface FlowRunEvent {
  flow_run_id: string;
  event_type: string;
  node_key: string | null;
  created_at: string;
}
