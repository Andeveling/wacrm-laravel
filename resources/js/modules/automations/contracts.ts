export interface AutomationContact {
  id: string;
  phone: string;
  name?: string;
}

export type AutomationTriggerType =
  | 'new_message_received'
  | 'first_inbound_message'
  | 'keyword_match'
  | 'new_contact_created'
  | 'conversation_assigned'
  | 'tag_added'
  | 'time_based'
  | 'interactive_reply';

export interface Automation {
  id: string;
  name: string;
  description?: string | null;
  trigger_type: AutomationTriggerType;
  connection_mode?: 'pinned' | 'trigger';
  connection_id?: string | null;
  is_active: boolean;
  execution_count: number;
  last_executed_at?: string | null;
  created_at: string | null;
  updated_at: string | null;
  steps: AutomationStep[];
}

export interface AutomationConnection {
  id: string;
  phone_number_id: string;
  is_default: boolean;
}

export interface AutomationStep {
  id: string;
  step_type: AutomationStepType;
  step_config: Record<string, unknown>;
  position: number;
}

export type AutomationStepType =
  | 'send_message'
  | 'wait'
  | 'condition'
  | 'add_tag'
  | 'assign_conversation';

export type AutomationLogStatus = 'success' | 'partial' | 'failed';

export interface AutomationLogStepResult {
  step_id: string;
  step_type: AutomationStepType;
  status: 'success' | 'skipped' | 'failed';
  detail?: string;
}

export interface AutomationLog {
  id: string;
  automation_id: string;
  contact_id: string | null;
  contact?: AutomationContact | null;
  trigger_event: string;
  steps_executed: AutomationLogStepResult[];
  status: AutomationLogStatus;
  error_message?: string | null;
  created_at: string | null;
}
