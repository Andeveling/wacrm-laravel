import type { WhatsappConnection, WhatsappReadiness } from './types';

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

export function hasActiveDefault(
  connections: readonly WhatsappConnection[],
): boolean {
  return connections.some(
    (connection) => connection.is_default && connection.readiness === 'active',
  );
}

export function canDesignateDefault(connection: WhatsappConnection): boolean {
  return connection.readiness === 'active' && !connection.is_default;
}

export function needsAttention(readiness: WhatsappReadiness): boolean {
  return readiness === 'attention_required' || readiness === 'disconnected';
}
