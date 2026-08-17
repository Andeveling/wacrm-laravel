import { Eye, EyeOff } from 'lucide-react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function PhoneNumberIdField({
  value,
  error,
  onChange,
}: {
  value: string;
  error?: string;
  onChange: (value: string) => void;
}) {
  const id = useId();

  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>Phone Number ID</Label>
      <Input
        id={id}
        name="phone_number_id"
        data-testid="whatsapp-phone-number-id"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder="100234567890123"
        required
        aria-invalid={Boolean(error)}
      />
      <InputError message={error} />
    </div>
  );
}

export function WabaIdField({
  value,
  error,
  onChange,
}: {
  value: string;
  error?: string;
  onChange: (value: string) => void;
}) {
  const id = useId();

  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>WABA ID</Label>
      <Input
        id={id}
        name="waba_id"
        data-testid="whatsapp-waba-id"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder="100234567890456"
        required
        aria-invalid={Boolean(error)}
      />
      <InputError message={error} />
    </div>
  );
}

export function AccessTokenField({
  value,
  error,
  hasConnections,
  onChange,
}: {
  value: string;
  error?: string;
  hasConnections: boolean;
  onChange: (value: string) => void;
}) {
  const id = useId();
  const [showToken, setShowToken] = useState(false);

  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>Token de acceso de Meta</Label>
      <div className="relative">
        <Input
          id={id}
          name="access_token"
          data-testid="whatsapp-access-token"
          type={showToken ? 'text' : 'password'}
          value={value}
          onChange={(event) => onChange(event.target.value)}
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
      <InputError message={error} />
    </div>
  );
}

export function PinField({
  value,
  error,
  onChange,
}: {
  value: string;
  error?: string;
  onChange: (value: string) => void;
}) {
  const id = useId();

  return (
    <div className="grid gap-2 sm:max-w-xs">
      <Label htmlFor={id}>PIN de verificación en dos pasos</Label>
      <Input
        id={id}
        name="pin"
        data-testid="whatsapp-pin"
        inputMode="numeric"
        maxLength={6}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder="Opcional hasta registrar el número"
      />
      <p className="text-xs text-muted-foreground">
        Necesario para que Meta registre un número que todavía no está conectado
        a esta app.
      </p>
      <InputError message={error} />
    </div>
  );
}

export function ConfirmDefaultField({
  checked,
  onChange,
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  const id = useId();

  return (
    <label
      htmlFor={id}
      className="flex items-start gap-3 rounded-lg border p-3 text-sm"
    >
      <input
        id={id}
        type="checkbox"
        name="confirm_default"
        data-testid="whatsapp-confirm-default"
        className="mt-1"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
      />
      <span>
        <span className="font-medium text-foreground">
          Usar como remitente predeterminado
        </span>
        <span className="mt-1 block text-xs text-muted-foreground">
          Se aplicará cuando la conexión pase a Active tras el primer evento
          enrutado. No se elige otro número en silencio.
        </span>
      </span>
    </label>
  );
}
