import type { WhatsappConnection } from '../types';

export function ConnectionHealth({
  connection,
  testId,
}: {
  connection: WhatsappConnection;
  testId: string;
}) {
  if (!connection.health) {
    return null;
  }

  const failing =
    connection.health === 'attention' || connection.health === 'disconnected';

  return (
    <p
      className={`text-xs ${failing ? 'text-destructive' : 'text-muted-foreground'}`}
      data-testid={`whatsapp-health-${testId}`}
    >
      Salud: {connection.health}
      {connection.pending_default
        ? ' · Se marcará como predeterminado al activarse'
        : ''}
    </p>
  );
}
