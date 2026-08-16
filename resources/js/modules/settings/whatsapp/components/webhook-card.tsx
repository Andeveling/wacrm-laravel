import { Copy } from 'lucide-react';
import { toast } from 'sonner';
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

export function WebhookCard({
  webhookUrl,
  verifyToken,
}: {
  webhookUrl: string;
  verifyToken: string | null;
}) {
  const [, copy] = useClipboard();

  async function copyWebhookUrl() {
    if (await copy(webhookUrl)) {
      toast.success('URL copiada.');
    }
  }

  async function copyVerifyToken() {
    if (!verifyToken) {
      return;
    }

    if (await copy(verifyToken)) {
      toast.success('Verify token copiado.');
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Webhook global</CardTitle>
        <CardDescription>
          Configura esta URL y el verify token una sola vez en tu Meta App. El
          enrutamiento usa el Phone Number ID de cada entrega.
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
  );
}
