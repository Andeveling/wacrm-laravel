import { router, useForm } from '@inertiajs/react';
import { Copy, Loader2 } from 'lucide-react';
import { useId } from 'react';
import { toast } from 'sonner';
import StoreApiKey from '@/actions/App/Domain/Settings/Actions/StoreApiKey';
import { Button } from '@/components/ui/button';
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
import type { ApiScope } from '../api-key-contracts';
import { API_SCOPES, SCOPE_DESCRIPTIONS } from '../api-key-scopes';

export function CreateApiKeyDialog({
  open,
  newKeyPlaintext,
  onOpenChange,
}: {
  open: boolean;
  newKeyPlaintext: string | null;
  onOpenChange: (open: boolean) => void;
}) {
  const nameId = useId();
  const scopesId = useId();
  const [, copy] = useClipboard();
  const form = useForm<{ name: string; scopes: ApiScope[] }>({
    name: '',
    scopes: [],
  });

  function toggleScope(scope: ApiScope, checked: boolean) {
    form.setData(
      'scopes',
      checked
        ? [...form.data.scopes, scope]
        : form.data.scopes.filter((item) => item !== scope),
    );
  }

  function handleCreate() {
    if (!form.data.name.trim()) {
      toast.error('Dale un nombre a la llave.');
      return;
    }

    form.submit(StoreApiKey(), {
      preserveScroll: true,
      onError: () => toast.error('No se pudo crear la llave.'),
    });
  }

  function closeReveal() {
    form.reset();
    router.reload();
  }

  async function copyKey() {
    if (!newKeyPlaintext) {
      return;
    }

    if (await copy(newKeyPlaintext)) {
      toast.success('Llave copiada.');
    }
  }

  function handleOpenChange(next: boolean) {
    if (next) {
      return;
    }

    if (newKeyPlaintext) {
      closeReveal();
    } else {
      form.reset();
    }

    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        {newKeyPlaintext ? (
          <RevealKey
            plaintext={newKeyPlaintext}
            onCopy={copyKey}
            onDone={closeReveal}
          />
        ) : (
          <CreateKeyForm
            nameId={nameId}
            scopesId={scopesId}
            name={form.data.name}
            scopes={form.data.scopes}
            processing={form.processing}
            onNameChange={(value) => form.setData('name', value)}
            onToggleScope={toggleScope}
            onCancel={() => handleOpenChange(false)}
            onCreate={handleCreate}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

function RevealKey({
  plaintext,
  onCopy,
  onDone,
}: {
  plaintext: string;
  onCopy: () => void;
  onDone: () => void;
}) {
  const keyId = useId();

  return (
    <>
      <DialogHeader>
        <DialogTitle>Copia tu llave ahora</DialogTitle>
        <DialogDescription>
          No podrás volver a ver esta llave. Guárdala en un lugar seguro.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-1.5">
        <Label htmlFor={keyId}>Llave de API</Label>
        <div className="flex gap-2">
          <Input
            id={keyId}
            readOnly
            value={plaintext}
            className="font-mono text-xs"
            onFocus={(event) => event.currentTarget.select()}
          />
          <Button type="button" variant="outline" onClick={onCopy}>
            <Copy className="size-4" />
            Copiar
          </Button>
        </div>
      </div>

      <DialogFooter>
        <Button type="button" onClick={onDone}>
          Listo
        </Button>
      </DialogFooter>
    </>
  );
}

function CreateKeyForm({
  nameId,
  scopesId,
  name,
  scopes,
  processing,
  onNameChange,
  onToggleScope,
  onCancel,
  onCreate,
}: {
  nameId: string;
  scopesId: string;
  name: string;
  scopes: ApiScope[];
  processing: boolean;
  onNameChange: (value: string) => void;
  onToggleScope: (scope: ApiScope, checked: boolean) => void;
  onCancel: () => void;
  onCreate: () => void;
}) {
  return (
    <>
      <DialogHeader>
        <DialogTitle>Nueva llave de API</DialogTitle>
        <DialogDescription>
          Elige qué puede hacer esta llave. El token completo solo se muestra
          una vez.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor={nameId}>Nombre</Label>
          <Input
            id={nameId}
            name="name"
            value={name}
            maxLength={80}
            placeholder="ej. Integración de facturación"
            onChange={(event) => onNameChange(event.target.value)}
          />
        </div>

        <div className="space-y-2">
          <Label>Permisos</Label>
          <div className="space-y-2 rounded-md border p-3">
            {API_SCOPES.map((scope) => (
              <label
                key={scope}
                htmlFor={`${scopesId}-${scope}`}
                className="flex cursor-pointer items-start gap-2.5"
              >
                <Checkbox
                  id={`${scopesId}-${scope}`}
                  checked={scopes.includes(scope)}
                  onCheckedChange={(checked) =>
                    onToggleScope(scope, checked === true)
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
            Sin permisos seleccionados, la llave solo puede autenticar (ej. GET
            /api/v1/me).
          </p>
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="button" onClick={onCreate} disabled={processing}>
          {processing ? <Loader2 className="size-4 animate-spin" /> : null}
          Crear llave
        </Button>
      </DialogFooter>
    </>
  );
}
