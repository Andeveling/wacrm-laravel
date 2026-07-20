import { Head, router, useForm } from '@inertiajs/react';
import { Copy, KeyRound, Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
  destroy,
  store,
} from '@/actions/App/Http/Controllers/Settings/ApiKeysController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useClipboard } from '@/hooks/use-clipboard';
import { computeApiKeyStatus, getApiKeyStatus } from '@/lib/api-key-status';
import { API_SCOPES, SCOPE_DESCRIPTIONS } from '@/lib/api-keys/scopes';
import type { ApiKey, ApiScope } from '@/types';

function fmtDate(iso: string): string {
  return new Date(iso).toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

interface PageProps {
  keys: ApiKey[];
  canManage: boolean;
  newKeyPlaintext: string | null;
}

export default function ApiKeysPage({
  keys,
  canManage,
  newKeyPlaintext,
}: PageProps) {
  const [createOpen, setCreateOpen] = useState(false);
  const [revokingId, setRevokingId] = useState<string | null>(null);
  const [keyToRevoke, setKeyToRevoke] = useState<ApiKey | null>(null);
  const [, copy] = useClipboard();

  const form = useForm<{ name: string; scopes: ApiScope[] }>({
    name: '',
    scopes: [],
  });

  // A create redirects back with the plaintext flashed once — surface it
  // as soon as it shows up in props, then close the create form under it.
  useEffect(() => {
    if (newKeyPlaintext) {
      setCreateOpen(false);
    }
  }, [newKeyPlaintext]);

  function toggleScope(scope: ApiScope, checked: boolean) {
    form.setData(
      'scopes',
      checked
        ? [...form.data.scopes, scope]
        : form.data.scopes.filter((s) => s !== scope),
    );
  }

  function handleCreate() {
    if (!form.data.name.trim()) {
      toast.error('Dale un nombre a la llave.');
      return;
    }
    form.submit(store(), {
      preserveScroll: true,
      onError: () => toast.error('No se pudo crear la llave.'),
    });
  }

  function closeReveal() {
    form.reset();
    router.reload();
  }

  async function copyKey() {
    if (!newKeyPlaintext) return;
    if (await copy(newKeyPlaintext)) toast.success('Llave copiada.');
  }

  function handleRevoke() {
    if (!keyToRevoke) return;
    setRevokingId(keyToRevoke.id);
    router.delete(destroy(keyToRevoke.id), {
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
          {canManage && (
            <Button onClick={() => setCreateOpen(true)} className="shrink-0">
              <Plus className="size-4" />
              Nueva llave
            </Button>
          )}
        </div>

        {keys.length === 0 ? (
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
        ) : (
          <Card>
            <CardContent className="p-0">
              <ul className="divide-y">
                {keys.map((k) => {
                  const status = computeApiKeyStatus(k);
                  const inactive = status !== 'active';
                  const statusDisplay = getApiKeyStatus(status);
                  return (
                    <li
                      key={k.id}
                      className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4"
                    >
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                          <span
                            className={`truncate text-sm font-medium ${inactive ? 'text-muted-foreground line-through' : 'text-foreground'}`}
                          >
                            {k.name}
                          </span>
                          {status !== 'active' && (
                            <Badge
                              variant={statusDisplay.variant}
                              className={statusDisplay.classes}
                            >
                              {statusDisplay.label}
                            </Badge>
                          )}
                        </div>
                        <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                          {k.key_prefix}…
                        </p>
                        <div className="mt-1.5 flex flex-wrap gap-1">
                          {k.scopes.length === 0 ? (
                            <span className="text-xs text-muted-foreground">
                              Sin permisos específicos
                            </span>
                          ) : (
                            k.scopes.map((s) => (
                              <Badge
                                key={s}
                                variant="outline"
                                className="text-[10px]"
                              >
                                {s}
                              </Badge>
                            ))
                          )}
                        </div>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                          Creada {k.created_at ? fmtDate(k.created_at) : '—'} ·{' '}
                          {k.last_used_at
                            ? `Usada por última vez ${fmtDate(k.last_used_at)}`
                            : 'Nunca usada'}
                          {k.expires_at && status !== 'expired'
                            ? ` · Expira ${fmtDate(k.expires_at)}`
                            : ''}
                        </p>
                      </div>

                      {canManage && status === 'active' && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setKeyToRevoke(k)}
                          disabled={revokingId === k.id}
                          className="self-start border-destructive/40 bg-destructive/10 text-destructive hover:bg-destructive/20 sm:self-auto"
                        >
                          {revokingId === k.id ? (
                            <Loader2 className="size-4 animate-spin" />
                          ) : (
                            <Trash2 className="size-4" />
                          )}
                          Revocar
                        </Button>
                      )}
                    </li>
                  );
                })}
              </ul>
            </CardContent>
          </Card>
        )}
      </div>

      <Dialog
        open={createOpen || !!newKeyPlaintext}
        onOpenChange={(next) => {
          if (!next) {
            if (newKeyPlaintext) closeReveal();
            else form.reset();
            setCreateOpen(false);
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          {newKeyPlaintext ? (
            <>
              <DialogHeader>
                <DialogTitle>Copia tu llave ahora</DialogTitle>
                <DialogDescription>
                  No podrás volver a ver esta llave. Guárdala en un lugar
                  seguro.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-1.5">
                <Label>Llave de API</Label>
                <div className="flex gap-2">
                  <Input
                    readOnly
                    value={newKeyPlaintext}
                    className="font-mono text-xs"
                    onFocus={(e) => e.currentTarget.select()}
                  />
                  <Button type="button" variant="outline" onClick={copyKey}>
                    <Copy className="size-4" />
                    Copiar
                  </Button>
                </div>
              </div>

              <DialogFooter>
                <Button onClick={closeReveal}>Listo</Button>
              </DialogFooter>
            </>
          ) : (
            <>
              <DialogHeader>
                <DialogTitle>Nueva llave de API</DialogTitle>
                <DialogDescription>
                  Elige qué puede hacer esta llave. El token completo solo se
                  muestra una vez.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-4">
                <div className="space-y-1.5">
                  <Label htmlFor="api-key-name">Nombre</Label>
                  <Input
                    id="api-key-name"
                    value={form.data.name}
                    maxLength={80}
                    placeholder="ej. Integración de facturación"
                    onChange={(e) => form.setData('name', e.target.value)}
                  />
                </div>

                <div className="space-y-2">
                  <Label>Permisos</Label>
                  <div className="space-y-2 rounded-md border p-3">
                    {API_SCOPES.map((scope) => (
                      <label
                        key={scope}
                        htmlFor={`scope-${scope}`}
                        className="flex cursor-pointer items-start gap-2.5"
                      >
                        <Checkbox
                          id={`scope-${scope}`}
                          checked={form.data.scopes.includes(scope)}
                          onCheckedChange={(checked) =>
                            toggleScope(scope, checked === true)
                          }
                          className="mt-0.5"
                        />
                        <span className="min-w-0">
                          <span className="block font-mono text-xs text-foreground">
                            {scope}
                          </span>
                          <span className="block text-xs text-muted-foreground">
                            {SCOPE_DESCRIPTIONS[scope]}
                          </span>
                        </span>
                      </label>
                    ))}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    Sin permisos seleccionados, la llave solo puede autenticar
                    (ej. GET /api/v1/me).
                  </p>
                </div>
              </div>

              <DialogFooter>
                <Button
                  variant="outline"
                  onClick={() => {
                    form.reset();
                    setCreateOpen(false);
                  }}
                >
                  Cancelar
                </Button>
                <Button onClick={handleCreate} disabled={form.processing}>
                  {form.processing && (
                    <Loader2 className="size-4 animate-spin" />
                  )}
                  Crear llave
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog
        open={!!keyToRevoke}
        onOpenChange={(o) => !o && setKeyToRevoke(null)}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Revocar llave</DialogTitle>
            <DialogDescription>
              {keyToRevoke
                ? `¿Revocar «${keyToRevoke.name}»? Cualquier integración que la use dejará de funcionar de inmediato.`
                : null}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setKeyToRevoke(null)}>
              Cancelar
            </Button>
            <Button
              variant="destructive"
              onClick={handleRevoke}
              disabled={!!revokingId}
            >
              Revocar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

ApiKeysPage.layout = {
  breadcrumbs: [
    { title: 'Settings', href: '/settings' },
    { title: 'API Keys' },
  ],
};
