import { describe, expect, it } from 'vitest';
import {
  canDesignateDefault,
  hasActiveDefault,
  isAwaitingWebhook,
  needsAttention,
  STEP_LABELS,
  STEP_ORDER,
  shouldPollWhatsappSettings,
} from './model';
import type { WhatsappConnection, WhatsappReadiness } from './types';

const connection = (
  overrides: Partial<WhatsappConnection> = {},
): WhatsappConnection => ({
  id: 'connection-1',
  phone_number_id: '100234567890123',
  waba_id: '100234567890456',
  readiness: 'active',
  is_default: false,
  connected_at: null,
  registered_at: null,
  last_registration_error: null,
  ...overrides,
});

describe('hasActiveDefault', () => {
  it('is true only when a connection is both default and Active', () => {
    expect(
      hasActiveDefault([
        connection({ is_default: true, readiness: 'active' }),
        connection({ id: 'connection-2', readiness: 'webhook_waiting' }),
      ]),
    ).toBe(true);
  });

  it('is false when the designated default is not Active', () => {
    expect(
      hasActiveDefault([
        connection({ is_default: true, readiness: 'webhook_waiting' }),
      ]),
    ).toBe(false);
  });

  it('is false when an Active connection is not the designated default', () => {
    expect(hasActiveDefault([connection({ is_default: false })])).toBe(false);
  });

  it('is false when there are no connections', () => {
    expect(hasActiveDefault([])).toBe(false);
  });
});

describe('guided setup steps', () => {
  it('orders readiness as credentials, subscribed, webhook waiting, then Active', () => {
    expect(STEP_ORDER).toEqual([
      'credentials_verified',
      'subscribed',
      'webhook_waiting',
      'active',
    ]);
  });

  it('keeps the critical journey labels the operator already sees', () => {
    expect(STEP_LABELS.webhook_waiting).toBe('Esperando webhook');
    expect(STEP_LABELS.active).toBe('Activo');
  });
});

describe('connection actions', () => {
  it('lets the operator designate only an Active connection that is not already default', () => {
    expect(canDesignateDefault(connection({ readiness: 'active' }))).toBe(true);
    expect(
      canDesignateDefault(
        connection({ readiness: 'active', is_default: true }),
      ),
    ).toBe(false);
    expect(
      canDesignateDefault(connection({ readiness: 'webhook_waiting' })),
    ).toBe(false);
  });

  it('flags attention_required and disconnected as needing attention', () => {
    const flagged: WhatsappReadiness[] = ['attention_required', 'disconnected'];

    for (const readiness of flagged) {
      expect(needsAttention(readiness)).toBe(true);
    }

    expect(needsAttention('active')).toBe(false);
    expect(needsAttention('webhook_waiting')).toBe(false);
  });
});

describe('webhook waiting poll', () => {
  it('polls only while a connection is waiting for the first webhook', () => {
    expect(
      isAwaitingWebhook(connection({ readiness: 'webhook_waiting' })),
    ).toBe(true);
    expect(isAwaitingWebhook(connection({ readiness: 'active' }))).toBe(false);
    expect(
      shouldPollWhatsappSettings([
        connection({ readiness: 'active' }),
        connection({ id: 'connection-2', readiness: 'webhook_waiting' }),
      ]),
    ).toBe(true);
    expect(
      shouldPollWhatsappSettings([connection({ readiness: 'active' })]),
    ).toBe(false);
  });
});
