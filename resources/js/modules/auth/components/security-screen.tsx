import { Form, Head } from '@inertiajs/react';
import { useId, useRef } from 'react';
import UpdatePassword from '@/actions/App/Domain/Settings/Actions/UpdatePassword';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
/* @chisel-passkeys */
import type { Props as ManagePasskeysProps } from './manage-passkeys';
import ManagePasskeys from './manage-passkeys';
/* @end-chisel-passkeys */
/* @chisel-2fa */
import type { Props as ManageTwoFactorProps } from './manage-two-factor';
import ManageTwoFactor from './manage-two-factor';

/* @end-chisel-2fa */

type Props = {
  passwordRules: string;
} /* @chisel-passkeys */ & ManagePasskeysProps /* @end-chisel-passkeys */ /* @chisel-2fa */ &
  ManageTwoFactorProps /* @end-chisel-2fa */;

export default function Security(props: Props) {
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  const currentPasswordId = useId();
  const passwordId = useId();
  const passwordConfirmationId = useId();

  return (
    <>
      <Head title="Configuración de seguridad" />

      <h1 className="sr-only">Configuración de seguridad</h1>

      <div className="space-y-6">
        <Heading
          variant="small"
          title="Actualizar contraseña"
          description="Usa una contraseña larga y aleatoria para mantener tu cuenta segura"
        />

        <Form
          {...UpdatePassword.form()}
          options={{
            preserveScroll: true,
          }}
          resetOnError={[
            'password',
            'password_confirmation',
            'current_password',
          ]}
          resetOnSuccess
          onError={(errors) => {
            if (errors.password) {
              passwordInput.current?.focus();
            }

            if (errors.current_password) {
              currentPasswordInput.current?.focus();
            }
          }}
          className="space-y-6"
        >
          {({ errors, processing }) => (
            <>
              <div className="grid gap-2">
                <Label htmlFor={currentPasswordId}>Contraseña actual</Label>

                <PasswordInput
                  id={currentPasswordId}
                  ref={currentPasswordInput}
                  name="current_password"
                  className="mt-1 block w-full"
                  autoComplete="current-password"
                  placeholder="Contraseña actual"
                />

                <InputError message={errors.current_password} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor={passwordId}>Nueva contraseña</Label>

                <PasswordInput
                  id={passwordId}
                  ref={passwordInput}
                  name="password"
                  className="mt-1 block w-full"
                  autoComplete="new-password"
                  placeholder="Nueva contraseña"
                  passwordrules={props.passwordRules}
                />

                <InputError message={errors.password} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor={passwordConfirmationId}>
                  Confirmar contraseña
                </Label>

                <PasswordInput
                  id={passwordConfirmationId}
                  name="password_confirmation"
                  className="mt-1 block w-full"
                  autoComplete="new-password"
                  placeholder="Confirmar contraseña"
                  passwordrules={props.passwordRules}
                />

                <InputError message={errors.password_confirmation} />
              </div>

              <div className="flex items-center gap-4">
                <Button
                  disabled={processing}
                  data-test="update-password-button"
                >
                  Guardar
                </Button>
              </div>
            </>
          )}
        </Form>
      </div>

      {/* @chisel-2fa */}
      <ManageTwoFactor
        canManageTwoFactor={props.canManageTwoFactor}
        requiresConfirmation={props.requiresConfirmation}
        twoFactorEnabled={props.twoFactorEnabled}
      />
      {/* @end-chisel-2fa */}

      {/* @chisel-passkeys */}
      <ManagePasskeys
        canManagePasskeys={props.canManagePasskeys}
        passkeys={props.passkeys}
      />
      {/* @end-chisel-passkeys */}
    </>
  );
}

Security.layout = {
  breadcrumbs: [
    {
      title: 'Configuración de seguridad',
      href: edit(),
    },
  ],
};
