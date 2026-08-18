import { Form, Head } from '@inertiajs/react';
import { useId } from 'react';
/* @chisel-passkeys */
import {
  index as confirmOptions,
  store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import PasskeyVerify from './passkey-verify';
/* @end-chisel-passkeys */

export default function ConfirmPassword() {
  const passwordId = useId();
  return (
    <>
      <Head title="Confirma tu contraseña" />

      {/* @chisel-passkeys */}
      <PasskeyVerify
        routes={{
          options: confirmOptions(),
          submit: confirmStore(),
        }}
        label="Confirmar con llave de acceso"
        loadingLabel="Confirmando…"
        separator="O confirmar con contraseña"
      />
      {/* @end-chisel-passkeys */}

      <Form {...store.form()} resetOnSuccess={['password']}>
        {({ processing, errors }) => (
          <div className="space-y-6">
            <div className="grid gap-2">
              <Label htmlFor={passwordId}>Contraseña</Label>
              <PasswordInput
                id={passwordId}
                name="password"
                placeholder="Contraseña"
                autoComplete="current-password"
                autoFocus
              />

              <InputError message={errors.password} />
            </div>

            <div className="flex items-center">
              <Button
                className="w-full"
                disabled={processing}
                data-test="confirm-password-button"
              >
                {processing ? <Spinner /> : null}
                Confirmar
              </Button>
            </div>
          </div>
        )}
      </Form>
    </>
  );
}

ConfirmPassword.layout = {
  title: 'Confirma tu contraseña',
  description:
    'Esta es un área segura de la aplicación. Por favor confirma tu contraseña antes de continuar.',
};
