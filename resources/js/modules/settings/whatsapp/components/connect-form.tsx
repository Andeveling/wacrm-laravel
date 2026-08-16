import { useForm } from '@inertiajs/react';
import { Loader2, LockKeyhole } from 'lucide-react';
import { type FormEvent, type Ref, useImperativeHandle } from 'react';
import { toast } from 'sonner';
import ConnectWhatsappNumber from '@/actions/App/Domain/Meta/Actions/ConnectWhatsappNumber';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import type {
  WhatsappConnectDraft,
  WhatsappConnectFormData,
  WhatsappConnection,
} from '../types';
import {
  AccessTokenField,
  ConfirmDefaultField,
  PhoneNumberIdField,
  PinField,
  WabaIdField,
} from './connect-fields';

export type ConnectFormHandle = {
  fillRetry: (connection: WhatsappConnection) => void;
};

export function ConnectForm({
  ref,
  draft,
  hasConnections,
  hasActiveDefault,
}: {
  ref?: Ref<ConnectFormHandle>;
  draft?: WhatsappConnectDraft;
  hasConnections: boolean;
  hasActiveDefault: boolean;
}) {
  const form = useForm<WhatsappConnectFormData>({
    phone_number_id: draft?.phone_number_id ?? '',
    waba_id: draft?.waba_id ?? '',
    access_token: '',
    pin: '',
    confirm_default: !hasActiveDefault,
  });

  useImperativeHandle(ref, () => ({
    fillRetry(connection) {
      form.setData('phone_number_id', connection.phone_number_id ?? '');
      form.setData('waba_id', connection.waba_id ?? '');
      form.setData('access_token', '');
      form.setData('pin', '');
    },
  }));

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    form.submit(ConnectWhatsappNumber(), {
      preserveScroll: true,
      onError: () => toast.error('Revisa los datos de conexión.'),
      onSuccess: () => form.reset('access_token', 'pin'),
    });
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {hasConnections
            ? 'Añadir o reintentar un número'
            : 'Conectar primer número'}
        </CardTitle>
        <CardDescription>
          El token se cifra en el servidor y nunca se devuelve al navegador.
          Déjalo vacío para reintentar con el token guardado.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="grid gap-5">
          <div className="grid gap-4 sm:grid-cols-2">
            <PhoneNumberIdField
              value={form.data.phone_number_id}
              error={form.errors.phone_number_id}
              onChange={(value) => form.setData('phone_number_id', value)}
            />
            <WabaIdField
              value={form.data.waba_id}
              error={form.errors.waba_id}
              onChange={(value) => form.setData('waba_id', value)}
            />
          </div>
          <AccessTokenField
            value={form.data.access_token}
            error={form.errors.access_token}
            hasConnections={hasConnections}
            onChange={(value) => form.setData('access_token', value)}
          />
          <PinField
            value={form.data.pin}
            error={form.errors.pin}
            onChange={(value) => form.setData('pin', value)}
          />
          {hasActiveDefault ? null : (
            <ConfirmDefaultField
              checked={form.data.confirm_default}
              onChange={(checked) => form.setData('confirm_default', checked)}
            />
          )}
          <div className="flex items-center justify-between gap-3 border-t pt-4">
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
              <LockKeyhole className="size-3.5" />
              Solo Owner y Admin pueden gestionar las conexiones.
            </p>
            <Button
              type="submit"
              disabled={form.processing}
              data-testid="whatsapp-connect-submit"
            >
              {form.processing ? (
                <Loader2 className="size-4 animate-spin" />
              ) : null}
              {form.processing
                ? 'Validando…'
                : hasConnections
                  ? 'Validar y guardar'
                  : 'Validar y conectar'}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
