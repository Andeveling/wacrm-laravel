import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
  onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
  const passkeyNameId = useId();
  const [name, setName] = useState(() => {
    const ua = navigator.userAgent;

    const browser = [
      { pattern: /Edg|Edge/, name: 'Edge' },
      { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
      { pattern: /Firefox|FxiOS/, name: 'Firefox' },
      { pattern: /Chrome|CriOS/, name: 'Chrome' },
      { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
      { pattern: /iPhone/, name: 'iPhone' },
      { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
      { pattern: /Android/, name: 'Android' },
      { pattern: /Mac/, name: 'Mac' },
      { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
  });

  const [showForm, setShowForm] = useState(false);
  const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
      setName('');
      setShowForm(false);
      onSuccess();
    },
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!name.trim()) {
      return;
    }

    await register(name);
  };

  const handleCancel = () => {
    setShowForm(false);
    setName('');
  };

  if (!isSupported) {
    return (
      <div className="text-sm text-muted-foreground">
        Las llaves de acceso no son compatibles con este navegador.
      </div>
    );
  }

  if (!showForm) {
    return (
      <Button variant="outline" onClick={() => setShowForm(true)}>
        Agregar llave de acceso
      </Button>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
    >
      <div className="grid gap-2">
        <Label htmlFor={passkeyNameId}>Nombre de la llave de acceso</Label>
        <Input
          id={passkeyNameId}
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="ej., MacBook Pro, iPhone"
          className="mt-1 block w-full border-foreground/20"
          autoFocus
        />
        <p className="text-xs text-muted-foreground">
          Un nombre te ayuda a identificar esta llave de acceso más adelante.
        </p>
      </div>

      {error ? <InputError message={error} /> : null}

      <div className="flex gap-2">
        <Button type="submit" disabled={isLoading || !name.trim()}>
          {isLoading ? 'Registrando…' : 'Registrar llave de acceso'}
        </Button>
        <Button type="button" variant="ghost" onClick={handleCancel}>
          Cancelar
        </Button>
      </div>
    </form>
  );
}
