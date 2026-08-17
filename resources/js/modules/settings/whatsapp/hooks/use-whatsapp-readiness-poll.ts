import { usePoll } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { shouldPollWhatsappSettings } from '../model';
import type { WhatsappConnection } from '../types';

const POLL_MS = 3000;

export function useWhatsappReadinessPoll(connections: WhatsappConnection[]) {
  const waiting = shouldPollWhatsappSettings(connections);
  const readinessById = useRef(
    new Map(
      connections.map((connection) => [connection.id, connection.readiness]),
    ),
  );
  const { start, stop } = usePoll(
    POLL_MS,
    {
      only: ['connections'],
    },
    {
      autoStart: false,
      keepAlive: true,
    },
  );

  useEffect(() => {
    if (waiting) {
      start();
    } else {
      stop();
    }

    return () => stop();
  }, [waiting, start, stop]);

  useEffect(() => {
    for (const connection of connections) {
      const previous = readinessById.current.get(connection.id);

      if (previous === 'webhook_waiting' && connection.readiness === 'active') {
        toast.success('El webhook llegó. El número está activo.');
      }

      readinessById.current.set(connection.id, connection.readiness);
    }
  }, [connections]);

  return waiting;
}
