import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from './passkey-verify';

/* @end-chisel-passkeys */

type Props = {
  status?: string;
  canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
  return (
    <>
      <Head title="Iniciar sesión" />

      {/* @chisel-passkeys */}
      <PasskeyVerify
        label="Iniciar sesión con llave de acceso"
        loadingLabel="Autenticando…"
        separator="O continuar con correo electrónico"
      />
      {/* @end-chisel-passkeys */}

      <Form
        {...store.form()}
        resetOnSuccess={['password']}
        className="flex flex-col gap-6"
      >
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                  id="email"
                  type="email"
                  name="email"
                  required
                  autoFocus
                  tabIndex={0}
                  autoComplete="email"
                  placeholder="email@example.com"
                />
                <InputError message={errors.email} />
              </div>

              <div className="grid gap-2">
                <div className="flex items-center">
                  <Label htmlFor="password">Contraseña</Label>
                  {canResetPassword && (
                    <TextLink
                      href={request()}
                      className="ml-auto text-sm"
                      tabIndex={0}
                    >
                      ¿Olvidaste tu contraseña?
                    </TextLink>
                  )}
                </div>
                <PasswordInput
                  id="password"
                  name="password"
                  required
                  tabIndex={0}
                  autoComplete="current-password"
                  placeholder="Contraseña"
                />
                <InputError message={errors.password} />
              </div>

              <div className="flex items-center space-x-3">
                <Checkbox id="remember" name="remember" tabIndex={0} />
                <Label htmlFor="remember">Recuérdame</Label>
              </div>

              <Button
                type="submit"
                className="mt-4 w-full"
                tabIndex={0}
                disabled={processing}
                data-test="login-button"
              >
                {processing && <Spinner />}
                Iniciar sesión
              </Button>
            </div>

            {/* @chisel-registration */}
            <div className="text-center text-sm text-muted-foreground">
              ¿No tienes una cuenta?{' '}
              <TextLink href={register()} tabIndex={0}>
                Regístrate
              </TextLink>
            </div>
            {/* @end-chisel-registration */}
          </>
        )}
      </Form>

      {status && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          {status}
        </div>
      )}
    </>
  );
}

Login.layout = {
  title: 'Inicia sesión en tu cuenta',
  description: 'Ingresa tu correo electrónico y contraseña para iniciar sesión',
};
