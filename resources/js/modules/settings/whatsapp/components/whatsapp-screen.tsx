import { Head } from '@inertiajs/react';
import { CircleAlert, Radio, ShieldCheck } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { overview as settingsOverview } from '@/routes/settings';
import { useWhatsappReadinessPoll } from '../hooks/use-whatsapp-readiness-poll';
import { useWhatsappSettings } from '../hooks/use-whatsapp-settings';
import { hasActiveDefault } from '../model';
import type { WhatsappSettingsPageProps } from '../types';
import { ConnectForm, type ConnectFormHandle } from './connect-form';
import { ConnectionActions, ConnectionCard } from './connection-card';
import { DisconnectDialog } from './disconnect-dialog';
import { WebhookCard } from './webhook-card';

export default function WhatsappScreen({
  canManage,
  connections,
  webhookUrl,
  verifyToken = null,
  draft,
  status,
  notice,
  error,
}: WhatsappSettingsPageProps) {
  const connectForm = useRef<ConnectFormHandle>(null);
  const settings = useWhatsappSettings();
  const waitingForWebhook = useWhatsappReadinessPoll(connections);
  const hasConnections = connections.length > 0;
  const activeDefault = hasActiveDefault(connections);

  useEffect(() => {
    if (status) {
      toast.success(status);
    }
    if (notice) {
      toast.message(notice, { duration: 10000 });
    }
    if (error) {
      toast.error(error, { duration: 10000 });
    }
  }, [status, notice, error]);

  return (
    <>
      <Head title="WhatsApp" />
      <div className="max-w-3xl space-y-6">
        <Heading
          title="WhatsApp"
          description="Administra varios números y WABA. El remitente predeterminado solo cambia si lo eliges."
        />

        {error ? (
          <Alert variant="destructive">
            <CircleAlert className="size-4" />
            <AlertTitle>El último paso necesita atención</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        {notice ? (
          <Alert>
            <CircleAlert className="size-4" />
            <AlertTitle>Número guardado</AlertTitle>
            <AlertDescription>{notice}</AlertDescription>
          </Alert>
        ) : null}

        {waitingForWebhook ? (
          <Alert>
            <Radio className="size-4" />
            <AlertTitle>Esperando la primera entrega de Meta</AlertTitle>
            <AlertDescription>
              Esta pantalla se actualiza sola cuando llega un mensaje o un
              webhook de prueba a este número.
            </AlertDescription>
          </Alert>
        ) : null}

        {hasConnections ? (
          <div className="grid gap-3">
            {connections.map((connection) => (
              <ConnectionCard key={connection.id} connection={connection}>
                {canManage ? (
                  <ConnectionActions
                    connection={connection}
                    locked={settings.busyId !== null}
                    busy={settings.busyId === connection.id}
                    onRetry={() => connectForm.current?.fillRetry(connection)}
                    onSetDefault={() => settings.setDefault(connection)}
                    onDisconnect={() => settings.requestDisconnect(connection)}
                  />
                ) : null}
              </ConnectionCard>
            ))}
          </div>
        ) : (
          <Alert>
            <Radio className="size-4" />
            <AlertTitle>Sin números conectados</AlertTitle>
            <AlertDescription>
              El asistente guardará cada paso validado para que puedas retomarlo
              si Meta rechaza un paso posterior.
            </AlertDescription>
          </Alert>
        )}

        {canManage ? (
          <ConnectForm
            ref={connectForm}
            draft={draft}
            hasConnections={hasConnections}
            hasActiveDefault={activeDefault}
          />
        ) : (
          <Alert>
            <ShieldCheck className="size-4" />
            <AlertTitle>Vista de solo lectura</AlertTitle>
            <AlertDescription>
              Pídele a un Owner o Admin que conecte o rote las credenciales.
              Esta pantalla no expone ningún token.
            </AlertDescription>
          </Alert>
        )}

        <WebhookCard webhookUrl={webhookUrl} verifyToken={verifyToken} />
      </div>

      <DisconnectDialog
        connection={settings.connectionToDisconnect}
        busy={!!settings.busyId}
        onCancel={settings.cancelDisconnect}
        onConfirm={settings.disconnect}
      />
    </>
  );
}

WhatsappScreen.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'WhatsApp' },
  ],
};
