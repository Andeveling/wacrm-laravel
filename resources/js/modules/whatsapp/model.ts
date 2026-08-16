import type {
  WhatsappConnection,
  WhatsappReadiness,
  WhatsappRemediationKind,
  WhatsappRemediationVariant,
} from './contracts';

export const STEP_ORDER: WhatsappReadiness[] = [
  'credentials_verified',
  'subscribed',
  'webhook_waiting',
  'active',
];

export const STEP_LABELS: Record<WhatsappReadiness, string> = {
  credentials_verified: 'Credenciales verificadas',
  subscribed: 'WABA suscrito',
  webhook_waiting: 'Esperando webhook',
  active: 'Activo',
  attention_required: 'Requiere atención',
  disconnected: 'Desconectado',
};

export const ISSUE_LABELS: Record<string, string> = {
  missing_legacy_connection:
    'Esta conversación no tiene una conexión heredada única.',
  ambiguous_conversation_connection:
    'Hay más de una conexión posible. Elige cuál corresponde.',
  waba_claimed_by_another_account:
    'Este WABA ya pertenece a otro Account. Reconéctalo de forma explícita.',
  phone_number_claimed_by_another_account:
    'Este número ya pertenece a otro Account. Reconéctalo de forma explícita.',
  incomplete_legacy_config:
    'La configuración heredada está incompleta. Completa las credenciales y reconecta.',
};

const ASSIGNABLE_KINDS = new Set([
  'ambiguous_conversation_connection',
  'missing_legacy_connection',
]);

export function hasActiveDefault(
  connections: readonly WhatsappConnection[],
): boolean {
  return connections.some(
    (connection) => connection.is_default && connection.readiness === 'active',
  );
}

export function remediationVariant(
  kind: WhatsappRemediationKind | string,
): WhatsappRemediationVariant {
  return ASSIGNABLE_KINDS.has(kind) ? 'assign' : 'acknowledge';
}

export function canDesignateDefault(connection: WhatsappConnection): boolean {
  return connection.readiness === 'active' && !connection.is_default;
}

export function needsAttention(readiness: WhatsappReadiness): boolean {
  return readiness === 'attention_required' || readiness === 'disconnected';
}

export function issueLabel(kind: WhatsappRemediationKind | string): string {
  return ISSUE_LABELS[kind] ?? kind;
}
