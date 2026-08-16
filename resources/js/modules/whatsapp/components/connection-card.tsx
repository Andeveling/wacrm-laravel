import { Loader2, Star } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { canDesignateDefault } from '../model';
import type { WhatsappConnection } from '../types';
import { ConnectionFailure } from './connection-failure';
import { ConnectionHeader } from './connection-header';
import { ConnectionHealth } from './connection-health';
import { ReadinessSteps } from './readiness-steps';

export function ConnectionCard({
  connection,
  children,
}: {
  connection: WhatsappConnection;
  children?: ReactNode;
}) {
  const testId = connection.phone_number_id ?? connection.id;

  return (
    <Card data-testid={`whatsapp-connection-${testId}`}>
      <CardContent className="grid gap-4 p-4">
        <ConnectionHeader connection={connection} testId={testId} />
        <ReadinessSteps readiness={connection.readiness} />
        <ConnectionHealth connection={connection} testId={testId} />
        <ConnectionFailure connection={connection} />
        {children}
      </CardContent>
    </Card>
  );
}

export function ConnectionActions({
  connection,
  locked,
  busy,
  onRetry,
  onSetDefault,
  onDisconnect,
}: {
  connection: WhatsappConnection;
  locked: boolean;
  busy: boolean;
  onRetry: () => void;
  onSetDefault: () => void;
  onDisconnect: () => void;
}) {
  return (
    <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-3">
      {connection.readiness !== 'active' ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={locked}
          onClick={onRetry}
        >
          {connection.readiness === 'disconnected'
            ? 'Reconectar'
            : 'Reintentar'}
        </Button>
      ) : null}
      {canDesignateDefault(connection) ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={locked}
          onClick={onSetDefault}
        >
          {busy ? (
            <Loader2 className="size-4 animate-spin" />
          ) : (
            <Star className="size-4" />
          )}
          Usar como predeterminado
        </Button>
      ) : null}
      {connection.readiness !== 'disconnected' ? (
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={locked}
          onClick={onDisconnect}
          className="border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/20"
        >
          {busy ? <Loader2 className="size-4 animate-spin" /> : null}
          Desconectar
        </Button>
      ) : null}
    </div>
  );
}
