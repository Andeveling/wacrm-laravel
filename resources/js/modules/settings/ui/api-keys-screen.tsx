import { Head, router } from '@inertiajs/react';
import { KeyRound, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import DestroyApiKey from '@/actions/App/Domain/Settings/Actions/DestroyApiKey';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { overview as settingsOverview } from '@/routes/settings';
import type { ApiKey, ApiKeysPageProps } from '../api-key-contracts';
import { ApiKeyRow } from './api-key-row';
import { CreateApiKeyDialog } from './create-api-key-dialog';
import { RevokeApiKeyDialog } from './revoke-api-key-dialog';

export default function ApiKeysScreen({
  keys,
  canManage,
  newKeyPlaintext,
}: ApiKeysPageProps) {
  const [createOpen, setCreateOpen] = useState(false);
  const [revokingId, setRevokingId] = useState<string | null>(null);
  const [keyToRevoke, setKeyToRevoke] = useState<ApiKey | null>(null);

  useEffect(() => {
    if (newKeyPlaintext) {
      setCreateOpen(false);
    }
  }, [newKeyPlaintext]);

  function handleRevoke() {
    if (!keyToRevoke) {
      return;
    }

    setRevokingId(keyToRevoke.id);
    router.delete(DestroyApiKey(keyToRevoke.id), {
      preserveScroll: true,
      onSuccess: () => setKeyToRevoke(null),
      onError: () => toast.error('No se pudo revocar la llave.'),
      onFinish: () => setRevokingId(null),
    });
  }

  return (
    <>
      <Head title="API Keys" />

      <div className="space-y-4">
        <div className="flex items-start justify-between gap-3">
          <Heading
            title="API Keys"
            description="Tokens de acceso programático a la API pública (/api/v1)."
          />
          {canManage ? (
            <Button
              type="button"
              onClick={() => setCreateOpen(true)}
              className="shrink-0"
            >
              <Plus className="size-4" />
              Nueva llave
            </Button>
          ) : null}
        </div>

        {keys.length === 0 ? (
          <EmptyKeys canManage={canManage} />
        ) : (
          <Card>
            <CardContent className="p-0">
              <ul className="divide-y">
                {keys.map((apiKey) => (
                  <ApiKeyRow
                    key={apiKey.id}
                    apiKey={apiKey}
                    revoking={revokingId === apiKey.id}
                    onRevoke={
                      canManage ? () => setKeyToRevoke(apiKey) : undefined
                    }
                  />
                ))}
              </ul>
            </CardContent>
          </Card>
        )}
      </div>

      <CreateApiKeyDialog
        open={createOpen || !!newKeyPlaintext}
        newKeyPlaintext={newKeyPlaintext}
        onOpenChange={setCreateOpen}
      />
      <RevokeApiKeyDialog
        apiKey={keyToRevoke}
        busy={!!revokingId}
        onCancel={() => setKeyToRevoke(null)}
        onConfirm={handleRevoke}
      />
    </>
  );
}

function EmptyKeys({ canManage }: { canManage: boolean }) {
  return (
    <Card>
      <CardContent className="flex flex-col items-center justify-center py-10 text-center">
        <KeyRound className="size-6 text-muted-foreground" />
        <p className="mt-2 text-sm text-muted-foreground">
          Sin llaves de API todavía.
        </p>
        <p className="mt-1 text-xs text-muted-foreground">
          {canManage
            ? 'Crea una para empezar a usar la API pública.'
            : 'Pídele a un administrador que cree una.'}
        </p>
      </CardContent>
    </Card>
  );
}

ApiKeysScreen.layout = {
  breadcrumbs: [
    { title: 'Settings', href: settingsOverview() },
    { title: 'API Keys' },
  ],
};
