import { Head, router, useForm } from '@inertiajs/react';
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
  Star,
  XCircle,
} from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { toast } from 'sonner';
import AssignLegacyWhatsappConversation from '@/actions/App/Domain/Meta/Actions/AssignLegacyWhatsappConversation';
import ConnectWhatsappNumber from '@/actions/App/Domain/Meta/Actions/ConnectWhatsappNumber';
import DisconnectWhatsappConnection from '@/actions/App/Domain/Meta/Actions/DisconnectWhatsappConnection';
import DismissLegacyWhatsappIssue from '@/actions/App/Domain/Meta/Actions/DismissLegacyWhatsappIssue';
import SetDefaultWhatsappConnection from '@/actions/App/Domain/Meta/Actions/SetDefaultWhatsappConnection';
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
  pending_default?: boolean;
  connected_at: string | null;
  registered_at: string | null;
  last_registration_error: string | null;
  last_failure?: string | null;
  health?: 'healthy' | 'pending' | 'attention' | 'disconnected';
};

type LegacyIssue = {
  id: string;
  kind: string;
  conversation_id: string | null;
  contact_name: string | null;
  action: string | null;
  candidate_connections: number | null;
};

type PageProps = {
  canManage: boolean;
  connections: Connection[];
  legacyIssues: LegacyIssue[];
  webhookUrl: string;
  verifyToken?: string | null;
  status?: string | null;
  error?: string | null;
};

const ISSUE_LABELS: Record<string, string> = {
  missing_legacy_connection:
    'Esta conversación no tiene una conexión heredada única.',
  ambiguous_conversation_connection:
    'Hay más de una conexión posible. Elige cuál corresponde.',
  waba_claimed_by_another_account:
    'Este WABA ya pertenece a otro Account. Reconéctalo de forma explícita.',
  phone_number_claimed_by_another_account:
    'Este número ya pertenece a otro Account. Reconéctalo de forma explícita.',
  incomplete_legacy_config:
    'La configuración heredada está incompleta. Completa las credenciales y reconecta.',
};

type FormData = {
  phone_number_id: string;
  waba_id: string;
  access_token: string;
  pin: string;
  confirm_default: boolean;
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
  legacyIssues = [],
  webhookUrl,
  verifyToken = null,
  status,
  error,
}: PageProps) {
  const [, copy] = useClipboard();
  const inputId = useId();
  const [showToken, setShowToken] = useState(false);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [connectionToDisconnect, setConnectionToDisconnect] =
    useState<Connection | null>(null);
  const hasConnections = connections.length > 0;
  const hasActiveDefault = connections.some(
    (connection) => connection.is_default && connection.readiness === 'active',
  );
  const form = useForm<FormData>({
    phone_number_id: '',
    waba_id: '',
    access_token: '',
    pin: '',
    confirm_default: !hasActiveDefault,
  });

  useEffect(() => {
    if (status) toast.success(status);
    if (error) toast.error(error, { duration: 10000 });
  }, [status, error]);

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

  async function copyVerifyToken() {
    if (!verifyToken) return;
    if (await copy(verifyToken)) toast.success('Verify token copiado.');
  }

  function setDefault(connection: Connection) {
    setBusyId(connection.id);
    router.patch(
      SetDefaultWhatsappConnection(connection.id),
      {},
      {
        preserveScroll: true,
        onError: () => toast.error('No se pudo actualizar el remitente.'),
        onFinish: () => setBusyId(null),
      },
    );
  }

  function disconnect() {
    if (!connectionToDisconnect) return;
    setBusyId(connectionToDisconnect.id);
    router.delete(DisconnectWhatsappConnection(connectionToDisconnect.id), {
      preserveScroll: true,
      onSuccess: () => setConnectionToDisconnect(null),
      onError: () => toast.error('No se pudo desconectar el número.'),
      onFinish: () => setBusyId(null),
    });
  }

  return (
    <>
      <Head title="WhatsApp" />
      <div className="max-w-3xl space-y-6">
        <Heading
          title="WhatsApp"
          description="Administra varios números y WABA. El remitente predeterminado solo cambia si lo eliges."
        />

        {!!error && (
          <Alert variant="destructive">
            <CircleAlert className="size-4" />
            <AlertTitle>El último paso necesita atención</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {legacyIssues.length > 0 ? (
          <Card data-testid="legacy-migration-issues">
            <CardHeader>
              <CardTitle>Remediación de migración</CardTitle>
              <CardDescription>
                Estos casos no se asignaron en silencio. Elige una conexión o
                márcalos como atendidos.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
              {legacyIssues.map((issue) => (
                <LegacyIssueRow
                  key={issue.id}
                  issue={issue}
                  connections={connections}
                  canManage={canManage}
                  busy={busyId === issue.id}
                  onBusy={setBusyId}
                />
              ))}
            </CardContent>
          </Card>
        ) : null}

        {connections.length > 0 ? (
          <div className="grid gap-3">
            {connections.map((item) => (
              <ConnectionCard
                key={item.id}
                connection={item}
                canManage={canManage}
                busy={busyId === item.id}
                onRetry={() => {
                  form.setData({
                    phone_number_id: item.phone_number_id ?? '',
                    waba_id: item.waba_id ?? '',
                    access_token: '',
                    pin: '',
                  });
                }}
                onSetDefault={() => setDefault(item)}
                onDisconnect={() => setConnectionToDisconnect(item)}
              />
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
                {hasConnections
                  ? 'Añadir o reintentar un número'
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
                    <Label htmlFor={`${inputId}-phone-id`}>
                      Phone Number ID
                    </Label>
                    <Input
                      id={`${inputId}-phone-id`}
                      name="phone_number_id"
                      data-testid="whatsapp-phone-number-id"
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
                    <Label htmlFor={`${inputId}-waba-id`}>WABA ID</Label>
                    <Input
                      id={`${inputId}-waba-id`}
                      name="waba_id"
                      data-testid="whatsapp-waba-id"
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
                  <Label htmlFor={`${inputId}-access-token`}>
                    Token de acceso de Meta
                  </Label>
                  <div className="relative">
                    <Input
                      id={`${inputId}-access-token`}
                      name="access_token"
                      data-testid="whatsapp-access-token"
                      type={showToken ? 'text' : 'password'}
                      value={form.data.access_token}
                      onChange={(event) =>
                        form.setData('access_token', event.target.value)
                      }
                      placeholder={
                        hasConnections
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
                  <Label htmlFor={`${inputId}-pin`}>
                    PIN de verificación en dos pasos
                  </Label>
                  <Input
                    id={`${inputId}-pin`}
                    name="pin"
                    data-testid="whatsapp-pin"
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

                {!hasActiveDefault ? (
                  <label className="flex items-start gap-3 rounded-lg border p-3 text-sm">
                    <input
                      type="checkbox"
                      name="confirm_default"
                      data-testid="whatsapp-confirm-default"
                      className="mt-1"
                      checked={form.data.confirm_default}
                      onChange={(event) =>
                        form.setData('confirm_default', event.target.checked)
                      }
                    />
                    <span>
                      <span className="font-medium text-foreground">
                        Usar como remitente predeterminado
                      </span>
                      <span className="mt-1 block text-xs text-muted-foreground">
                        Se aplicará cuando la conexión pase a Active tras el
                        primer evento enrutado. No se elige otro número en
                        silencio.
                      </span>
                    </span>
                  </label>
                ) : null}

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
                    {!!form.processing && (
                      <Loader2 className="size-4 animate-spin" />
                    )}
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
              Configura esta URL y el verify token una sola vez en tu Meta App.
              El enrutamiento usa el Phone Number ID de cada entrega.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4">
            <div className="grid gap-2">
              <Label>Callback URL</Label>
              <div className="flex gap-2">
                <Input
                  readOnly
                  value={webhookUrl}
                  data-testid="whatsapp-webhook-url"
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
            </div>
            {verifyToken ? (
              <div className="grid gap-2">
                <Label>Verify token</Label>
                <div className="flex gap-2">
                  <Input
                    readOnly
                    value={verifyToken}
                    data-testid="whatsapp-verify-token"
                    className="font-mono text-sm text-muted-foreground"
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={copyVerifyToken}
                    className="shrink-0"
                    aria-label="Copiar verify token"
                  >
                    <Copy className="size-4" />
                  </Button>
                </div>
              </div>
            ) : (
              <p className="text-xs text-muted-foreground">
                El verify token se configura en el entorno de la instalación
                (`META_WEBHOOK_VERIFY_TOKEN`).
              </p>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog
        open={!!connectionToDisconnect}
        onOpenChange={(open) => !open && setConnectionToDisconnect(null)}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Desconectar número</DialogTitle>
            <DialogDescription>
              {connectionToDisconnect
                ? `¿Desconectar ${connectionToDisconnect.phone_number_id}? El historial se conserva y no se elige otro remitente predeterminado.`
                : null}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="ghost"
              onClick={() => setConnectionToDisconnect(null)}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={disconnect}
              disabled={!!busyId}
            >
              Desconectar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function LegacyIssueRow({
  issue,
  connections,
  canManage,
  busy,
  onBusy,
}: {
  issue: LegacyIssue;
  connections: Connection[];
  canManage: boolean;
  busy: boolean;
  onBusy: (id: string | null) => void;
}) {
  const assignable =
    issue.kind === 'ambiguous_conversation_connection' ||
    issue.kind === 'missing_legacy_connection';
  const [connectionId, setConnectionId] = useState(connections[0]?.id ?? '');

  function assign() {
    if (!connectionId) return;
    onBusy(issue.id);
    router.post(
      AssignLegacyWhatsappConversation(issue.id),
      { connection_id: connectionId },
      {
        preserveScroll: true,
        onError: () => toast.error('No se pudo asignar la conversación.'),
        onFinish: () => onBusy(null),
      },
    );
  }

  function dismiss() {
    onBusy(issue.id);
    router.post(
      DismissLegacyWhatsappIssue(issue.id),
      {},
      {
        preserveScroll: true,
        onError: () => toast.error('No se pudo marcar el caso como atendido.'),
        onFinish: () => onBusy(null),
      },
    );
  }

  return (
    <div
      className="grid gap-3 rounded-lg border p-3"
      data-testid={`legacy-issue-${issue.id}`}
    >
      <div className="grid gap-1">
        <p className="text-sm font-medium text-foreground">
          {issue.contact_name ?? 'Configuración heredada'}
        </p>
        <p className="text-xs text-muted-foreground">
          {ISSUE_LABELS[issue.kind] ?? issue.kind}
        </p>
      </div>

      {canManage && assignable ? (
        <div className="flex flex-wrap items-end gap-2">
          <div className="grid min-w-48 flex-1 gap-1">
            <Label htmlFor={`legacy-connection-${issue.id}`}>Conexión</Label>
            <Select
              value={connectionId}
              onValueChange={setConnectionId}
              disabled={busy || connections.length === 0}
            >
              <SelectTrigger
                id={`legacy-connection-${issue.id}`}
                data-testid={`legacy-issue-connection-${issue.id}`}
                className="w-full"
              >
                <SelectValue placeholder="Selecciona una conexión" />
              </SelectTrigger>
              <SelectContent>
                {connections.map((connection) => (
                  <SelectItem key={connection.id} value={connection.id}>
                    {connection.phone_number_id ?? connection.id}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button
            type="button"
            size="sm"
            disabled={busy || !connectionId}
            onClick={assign}
            data-testid={`legacy-issue-assign-${issue.id}`}
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            Asignar
          </Button>
        </div>
      ) : null}

      {canManage && !assignable ? (
        <div className="flex justify-end">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={busy}
            onClick={dismiss}
            data-testid={`legacy-issue-dismiss-${issue.id}`}
          >
            {busy ? <Loader2 className="size-4 animate-spin" /> : null}
            Marcar como atendido
          </Button>
        </div>
      ) : null}
    </div>
  );
}

function ConnectionCard({
  connection,
  canManage,
  busy,
  onRetry,
  onSetDefault,
  onDisconnect,
}: {
  connection: Connection;
  canManage: boolean;
  busy: boolean;
  onRetry: () => void;
  onSetDefault: () => void;
  onDisconnect: () => void;
}) {
  const currentStep = STEP_ORDER.indexOf(connection.readiness);
  const attention =
    connection.readiness === 'attention_required' ||
    connection.readiness === 'disconnected';

  return (
    <Card
      data-testid={`whatsapp-connection-${connection.phone_number_id ?? connection.id}`}
    >
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
          <div className="flex flex-wrap items-center gap-2">
            {connection.is_default ? (
              <Badge variant="outline">
                <Star className="size-3" />
                Predeterminado
              </Badge>
            ) : null}
            <Badge
              data-testid={`whatsapp-readiness-${connection.phone_number_id ?? connection.id}`}
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

        {connection.health ? (
          <p
            className={`text-xs ${connection.health === 'attention' || connection.health === 'disconnected' ? 'text-destructive' : 'text-muted-foreground'}`}
            data-testid={`whatsapp-health-${connection.phone_number_id ?? connection.id}`}
          >
            Salud: {connection.health}
            {connection.pending_default
              ? ' · Se marcará como predeterminado al activarse'
              : ''}
          </p>
        ) : null}

        {!!(connection.last_failure || connection.last_registration_error) && (
          <p className="text-xs text-destructive">
            {connection.last_failure || connection.last_registration_error}
          </p>
        )}

        {canManage ? (
          <div className="flex flex-wrap items-center justify-end gap-2 border-t pt-3">
            {connection.readiness !== 'active' ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={busy}
                onClick={onRetry}
              >
                {connection.readiness === 'disconnected'
                  ? 'Reconectar'
                  : 'Reintentar'}
              </Button>
            ) : null}
            {connection.readiness === 'active' && !connection.is_default ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={busy}
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
                disabled={busy}
                onClick={onDisconnect}
                className="border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/20"
              >
                {busy ? <Loader2 className="size-4 animate-spin" /> : null}
                Desconectar
              </Button>
            ) : null}
          </div>
        ) : null}
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
