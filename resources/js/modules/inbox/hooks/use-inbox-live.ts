import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { echoClient } from '@/lib/echo';
import type { InboxMessagePayload } from '../model';

function isInboxMessagePayload(value: unknown): value is InboxMessagePayload {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const payload = value as Partial<InboxMessagePayload>;

  return (
    typeof payload.message === 'object' &&
    payload.message !== null &&
    typeof payload.message.id === 'string' &&
    typeof payload.message.conversation_id === 'string' &&
    typeof payload.conversation === 'object' &&
    payload.conversation !== null &&
    typeof payload.conversation.id === 'string'
  );
}

export function useInboxLive(
  onMessage: (payload: InboxMessagePayload) => void,
): void {
  const accountId = usePage().props.currentAccount?.id ?? null;

  useEffect(() => {
    const client = echoClient();

    if (accountId === null || client === null) {
      return;
    }

    const channel = client.private(`accounts.${accountId}`);

    channel.listen('.inbox.message', (value: unknown) => {
      if (isInboxMessagePayload(value)) {
        onMessage(value);
      }
    });

    return () => {
      channel.stopListening('.inbox.message');
      client.leave(`accounts.${accountId}`);
    };
  }, [accountId, onMessage]);
}
