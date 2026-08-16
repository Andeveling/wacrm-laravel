import type { WhatsappConnection } from '../types';

export function ConnectionFailure({
  connection,
}: {
  connection: WhatsappConnection;
}) {
  const message = connection.last_failure || connection.last_registration_error;

  if (!message) {
    return null;
  }

  return <p className="text-xs text-destructive">{message}</p>;
}
