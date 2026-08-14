import { Head, useForm } from '@inertiajs/react';
import {
  CheckCircle2,
  CircleAlert,
  Copy,
  Eye,
  EyeOff,
  Loader2,
  LockKeyhole,
  Radio,
  ShieldCheck,
  XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ConnectWhatsappNumber from '@/actions/App/Domain/Meta/Actions/ConnectWhatsappNumber';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { overview as settingsOverview } from '@/routes/settings';

type Readiness =
  | 'credentials_verified'
  | 'subscribed'
  | 'webhook_waiting'
  | 'active'
  | 'attention_required'
  | 'disconnected';

type Connection = {
  id: string;
  phone_number_id: string | null;
  waba_id: string | null;
  readiness: Readiness;
  is_default: boolean;
  connected_at: string | null;
  registered_at: string | null;
  last_registration_error: string | null;
};

type PageProps = {
  canManage: boolean;
  connections: Connection[];
  webhookUrl: string;
  status?: string | null;
  error?: string | null;
};

type FormData = {
  phone_number_id: string;
  waba_id: string;
  access_token: string;
  pin: string;
};

const STEP_ORDER: Readiness[] = [
  'credentials_verified',
  'subscribed',
  'webhook_waiting',
  'active',
];

const STEP_LABELS: Record<Readiness, string> = {
  credentials_verified: 'Credenciales verificadas',
  subscribed: 'WABA suscrito',
  webhook_waiting: 'Esperando webhook',
  active: 'Activo',
  attention_required: 'Requiere atención',
  disconnected: 'Desconectado',
};

export default function Whatsapp({
  canManage,
  connections,
  webhookUrl,
  status,
  error,
}: PageProps) {
  const [, copy] = useClipboard();
  const [showToken, setShowToken] = useState(false);
  const connection = connections[0];
  const form = useForm<FormData>({
    phone_number_id: connection?.phone_number_id ?? '',
    waba_id: connection?.waba_id ?? '',
    access_token: '',
    pin: '',
  });

  useEffect(() => {
    if (status) toast.success(status);
    if (error) toast.error(error, { duration: 10000 });
  }, [status, error]);

  useEffect(() => {
    if (!connection) return;
    if (!form.data.phone_number_id)
      form.setData('phone_number_id', connection.phone_number_id ?? '');
    if (!form.data.waba_id) form.setData('waba_id', connection.waba_id ?? '');
  }, [connection, form]);

  function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    form.submit(ConnectWhatsappNumber(), {
      preserveScroll: true,
      onError: () => toast.error('Revisa los datos de conexión.'),
      onSuccess: () => form.reset('access_token', 'pin'),
    });
  }

  async function copyWebhookUrl() {
    if (await copy(webhookUrl)) toast.success('URL copiada.');
  }

  return (
    <>
      <Head title="WhatsApp" />
      <div className="max-w-3xl space-y-6">
        <Heading
          title="WhatsApp"
          description="Conecta un número de WhatsApp Business y valida cada paso con Meta."
        />

        {error && (
          <Alert variant="destructive">
            <CircleAlert className="size-4" />
            <AlertTitle>El último paso necesita atención</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {connections.length > 0 ? (
          <div className="grid gap-3">
            {connections.map((item) => (
              <ConnectionCard key={item.id} connection={item} />
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
          <Card>
            <CardHeader>
              <CardTitle>
                {connection
                  ? 'Reintentar o rotar credenciales'
                  : 'Conectar primer número'}
              </CardTitle>
              <CardDescription>
                El token se cifra en el servidor y nunca se devuelve al
                navegador. Déjalo vacío para reintentar con el token guardado.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="grid gap-5">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="grid gap-2">
                    <Label htmlFor="wa-phone-id">Phone Number ID</Label>
                    <Input
                      id="wa-phone-id"
                      name="phone_number_id"
                      value={form.data.phone_number_id}
                      onChange={(event) =>
                        form.setData('phone_number_id', event.target.value)
                      }
                      placeholder="100234567890123"
                      required
                      aria-invalid={Boolean(form.errors.phone_number_id)}
                    />
                    <InputError message={form.errors.phone_number_id} />
                  </div>
                  <div className="grid gap-2">
                    <Label htmlFor="wa-waba-id">WABA ID</Label>
                    <Input
                      id="wa-waba-id"
                      name="waba_id"
                      value={form.data.waba_id}
                      onChange={(event) =>
                        form.setData('waba_id', event.target.value)
                      }
                      placeholder="100234567890456"
                      required
                      aria-invalid={Boolean(form.errors.waba_id)}
                    />
                    <InputError message={form.errors.waba_id} />
                  </div>
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="wa-access-token">
                    Token de acceso de Meta
                  </Label>
                  <div className="relative">
                    <Input
                      id="wa-access-token"
                      name="access_token"
                      type={showToken ? 'text' : 'password'}
                      value={form.data.access_token}
                      onChange={(event) =>
                        form.setData('access_token', event.target.value)
                      }
                      placeholder={
                        connection
                          ? 'Vacío para usar el token guardado'
                          : 'Token de sistema de Meta'
                      }
                      className="pr-10"
                      autoComplete="new-password"
                    />
                    <button
                      type="button"
                      aria-label={showToken ? 'Ocultar token' : 'Mostrar token'}
                      onClick={() => setShowToken((visible) => !visible)}
                      className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                      {showToken ? (
                        <EyeOff className="size-4" />
                      ) : (
                        <Eye className="size-4" />
                      )}
                    </button>
                  </div>
                  <InputError message={form.errors.access_token} />
                </div>

                <div className="grid gap-2 sm:max-w-xs">
                  <Label htmlFor="wa-pin">
                    PIN de verificación en dos pasos
                  </Label>
                  <Input
                    id="wa-pin"
                    name="pin"
                    inputMode="numeric"
                    maxLength={6}
                    value={form.data.pin}
                    onChange={(event) =>
                      form.setData('pin', event.target.value)
                    }
                    placeholder="Opcional hasta registrar el número"
                  />
                  <p className="text-xs text-muted-foreground">
                    Necesario para que Meta registre un número que todavía no
                    está conectado a esta app.
                  </p>
                  <InputError message={form.errors.pin} />
                </div>

                <div className="flex items-center justify-between gap-3 border-t pt-4">
                  <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <LockKeyhole className="size-3.5" />
                    Solo Owner y Admin pueden cambiar la conexión.
                  </p>
                  <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                      <Loader2 className="size-4 animate-spin" />
                    )}
                    {form.processing
                      ? 'Validando…'
                      : connection
                        ? 'Continuar conexión'
                        : 'Validar y conectar'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
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

        <Card>
          <CardHeader>
            <CardTitle>Webhook global</CardTitle>
            <CardDescription>
              Configura esta URL una sola vez en tu Meta App. El enrutamiento
              usa el Phone Number ID de cada entrega.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex gap-2">
              <Input
                readOnly
                value={webhookUrl}
                className="font-mono text-sm text-muted-foreground"
              />
              <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={copyWebhookUrl}
                className="shrink-0"
                aria-label="Copiar URL del webhook"
              >
                <Copy className="size-4" />
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

function ConnectionCard({ connection }: { connection: Connection }) {
  const currentStep = STEP_ORDER.indexOf(connection.readiness);
  const attention =
    connection.readiness === 'attention_required' ||
    connection.readiness === 'disconnected';

  return (
    <Card>
      <CardContent className="grid gap-4 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="font-medium text-foreground">
              {connection.phone_number_id ?? 'Número pendiente'}
            </p>
            <p className="text-xs text-muted-foreground">
              WABA: {connection.waba_id ?? 'pendiente de validación'}
            </p>
          </div>
          <Badge
            variant={
              attention
                ? 'destructive'
                : connection.readiness === 'active'
                  ? 'default'
                  : 'secondary'
            }
          >
            {connection.readiness === 'active' ? (
              <CheckCircle2 className="size-3" />
            ) : attention ? (
              <XCircle className="size-3" />
            ) : (
              <ShieldCheck className="size-3" />
            )}
            {STEP_LABELS[connection.readiness]}
          </Badge>
        </div>

        <div className="grid gap-2 sm:grid-cols-4">
          {STEP_ORDER.map((step, index) => {
            const completed = currentStep >= index && !attention;
            return (
              <div key={step} className="grid gap-1">
                <div
                  className={`h-1 rounded-full ${completed ? 'bg-primary' : 'bg-muted'}`}
                />
                <span
                  className={`text-[11px] ${completed ? 'text-foreground' : 'text-muted-foreground'}`}
                >
                  {STEP_LABELS[step]}
                </span>
              </div>
            );
          })}
        </div>

        {connection.last_registration_error && (
          <p className="text-xs text-destructive">
            {connection.last_registration_error}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

Whatsapp.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'WhatsApp' },
  ],
};
