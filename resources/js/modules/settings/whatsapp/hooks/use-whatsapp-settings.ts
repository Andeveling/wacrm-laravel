import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import DisconnectWhatsappConnection from '@/actions/App/Domain/Meta/Actions/DisconnectWhatsappConnection';
import SetDefaultWhatsappConnection from '@/actions/App/Domain/Meta/Actions/SetDefaultWhatsappConnection';
import type { WhatsappConnection } from '../types';

export function useWhatsappSettings() {
  const [busyId, setBusyId] = useState<string | null>(null);
  const busyRef = useRef<string | null>(null);
  const [connectionToDisconnect, setConnectionToDisconnect] =
    useState<WhatsappConnection | null>(null);

  function begin(id: string): boolean {
    if (busyRef.current) {
      return false;
    }

    busyRef.current = id;
    setBusyId(id);

    return true;
  }

  function finish() {
    busyRef.current = null;
    setBusyId(null);
  }

  function setDefault(connection: WhatsappConnection) {
    if (!begin(connection.id)) {
      return;
    }

    router.patch(
      SetDefaultWhatsappConnection(connection.id),
      {},
      {
        preserveScroll: true,
        onError: () => toast.error('No se pudo actualizar el remitente.'),
        onFinish: finish,
      },
    );
  }

  function disconnect() {
    if (!connectionToDisconnect || !begin(connectionToDisconnect.id)) {
      return;
    }

    router.delete(DisconnectWhatsappConnection(connectionToDisconnect.id), {
      preserveScroll: true,
      onSuccess: () => setConnectionToDisconnect(null),
      onError: () => toast.error('No se pudo desconectar el número.'),
      onFinish: finish,
    });
  }

  return {
    busyId,
    connectionToDisconnect,
    setDefault,
    disconnect,
    requestDisconnect: setConnectionToDisconnect,
    cancelDisconnect: () => setConnectionToDisconnect(null),
  };
}
