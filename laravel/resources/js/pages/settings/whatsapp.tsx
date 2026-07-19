import { Head } from '@inertiajs/react';
import {
  CheckCircle2,
  Copy,
  Eye,
  EyeOff,
  Loader2,
  XCircle,
  Zap,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

const MASKED_TOKEN = '••••••••••••••••';

export default function Whatsapp() {
  const [, copy] = useClipboard();
  const [connected, setConnected] = useState(false);
  const [testing, setTesting] = useState(false);

  const [phoneNumberId, setPhoneNumberId] = useState('');
  const [wabaId, setWabaId] = useState('');
  const [accessToken, setAccessToken] = useState('');
  const [verifyToken, setVerifyToken] = useState('');
  const [showToken, setShowToken] = useState(false);

  const webhookUrl =
    typeof window !== 'undefined'
      ? `${window.location.origin}/api/whatsapp/webhook`
      : '';

  function handleSave() {
    if (!phoneNumberId.trim() || !accessToken.trim()) {
      toast.error('El ID del número y el token de acceso son obligatorios.');
      return;
    }
    setConnected(true);
    setAccessToken(MASKED_TOKEN);
    toast.success('Configuración de WhatsApp guardada.');
  }

  function handleTestConnection() {
    setTesting(true);
    setTimeout(() => {
      setTesting(false);
      toast.success('Conexión verificada correctamente.');
    }, 800);
  }

  function handleReset() {
    setConnected(false);
    setPhoneNumberId('');
    setWabaId('');
    setAccessToken('');
    setVerifyToken('');
    toast.success('Configuración de WhatsApp restablecida.');
  }

  async function handleCopyWebhookUrl() {
    if (await copy(webhookUrl)) toast.success('URL copiada.');
  }

  return (
    <>
      <Head title="WhatsApp" />

      <div className="max-w-2xl space-y-6">
        <Heading
          title="WhatsApp"
          description="Conexión con WhatsApp Business."
        />

        <Alert>
          <div className="flex items-center gap-2">
            {connected ? (
              <CheckCircle2 className="size-4 text-primary" />
            ) : (
              <XCircle className="size-4 text-destructive" />
            )}
            <AlertTitle>
              {connected ? 'Credenciales válidas' : 'No conectado'}
            </AlertTitle>
          </div>
          <AlertDescription>
            {connected
              ? 'Tu cuenta está conectada a WhatsApp Business.'
              : 'Completa y guarda las credenciales para conectar tu cuenta.'}
          </AlertDescription>
        </Alert>

        <Card>
          <CardHeader>
            <CardTitle>Credenciales de la API</CardTitle>
            <CardDescription>
              Datos de tu app de WhatsApp Business en Meta.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="wa-phone-id">ID del número de teléfono</Label>
              <Input
                id="wa-phone-id"
                placeholder="ej. 100234567890123"
                value={phoneNumberId}
                onChange={(e) => setPhoneNumberId(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="wa-waba-id">
                ID de la cuenta de WhatsApp Business (WABA)
              </Label>
              <Input
                id="wa-waba-id"
                placeholder="ej. 100234567890456"
                value={wabaId}
                onChange={(e) => setWabaId(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="wa-token">Token de acceso</Label>
              <div className="relative">
                <Input
                  id="wa-token"
                  type={showToken ? 'text' : 'password'}
                  placeholder="Token de acceso permanente"
                  value={accessToken}
                  onFocus={() => {
                    if (accessToken === MASKED_TOKEN) setAccessToken('');
                  }}
                  onChange={(e) => setAccessToken(e.target.value)}
                  className="pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowToken(!showToken)}
                  className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                  {showToken ? (
                    <EyeOff className="size-4" />
                  ) : (
                    <Eye className="size-4" />
                  )}
                </button>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="wa-verify-token">
                Token de verificación del webhook
              </Label>
              <Input
                id="wa-verify-token"
                placeholder="Cadena secreta que Meta reenvía para verificar"
                value={verifyToken}
                onChange={(e) => setVerifyToken(e.target.value)}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Webhook</CardTitle>
            <CardDescription>
              Configura esta URL en el panel de desarrolladores de Meta.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <Label>URL del webhook</Label>
              <div className="flex gap-2">
                <Input
                  readOnly
                  value={webhookUrl}
                  className="font-mono text-sm text-muted-foreground"
                />
                <Button
                  variant="outline"
                  size="icon"
                  onClick={handleCopyWebhookUrl}
                  className="shrink-0"
                >
                  <Copy className="size-4" />
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="flex flex-wrap gap-3">
          <Button onClick={handleSave}>Guardar configuración</Button>
          <Button
            variant="outline"
            onClick={handleTestConnection}
            disabled={testing || !connected}
          >
            {testing ? (
              <Loader2 className="size-4 animate-spin" />
            ) : (
              <Zap className="size-4" />
            )}
            Probar conexión
          </Button>
          {connected && (
            <Button
              variant="outline"
              onClick={handleReset}
              className="border-destructive/40 text-destructive hover:bg-destructive/10"
            >
              Restablecer
            </Button>
          )}
        </div>
      </div>
    </>
  );
}

Whatsapp.layout = {
  breadcrumbs: [
    { title: 'Settings', href: '/settings' },
    { title: 'WhatsApp' },
  ],
};
