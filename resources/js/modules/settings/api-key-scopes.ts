import type { ApiScope } from './api-key-contracts';

export const API_SCOPES: ApiScope[] = [
  'messages:send',
  'messages:read',
  'contacts:read',
  'contacts:write',
  'conversations:read',
  'broadcasts:send',
  'webhooks:manage',
];

export const SCOPE_DESCRIPTIONS: Record<ApiScope, string> = {
  'messages:send': 'Enviar mensajes de WhatsApp',
  'messages:read': 'Leer mensajes',
  'contacts:read': 'Leer contactos',
  'contacts:write': 'Crear y editar contactos',
  'conversations:read': 'Leer conversaciones',
  'broadcasts:send': 'Enviar difusiones',
  'webhooks:manage': 'Gestionar webhooks',
};
