import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
  passwordRules: string;
  invite: string | null;
};

export default function Register({ passwordRules, invite }: Props) {
  return (
    <>
      <Head title="Registrarse" />
      <Form
        {...store.form()}
        resetOnSuccess={['password', 'password_confirmation']}
        disableWhileProcessing
        className="flex flex-col gap-6"
      >
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              {invite !== null && (
                <>
                  <input type="hidden" name="invite" value={invite} />
                  <InputError message={errors.invite} />
                </>
              )}
              <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                  id="name"
                  type="text"
                  required
                  autoFocus
                  tabIndex="0"
                  autoComplete="name"
                  name="name"
                  placeholder="Nombre completo"
                />
                <InputError message={errors.name} className="mt-2" />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                  id="email"
                  type="email"
                  required
                  tabIndex="0"
                  autoComplete="email"
                  name="email"
                  placeholder="email@example.com"
                />
                <InputError message={errors.email} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password">Contraseña</Label>
                <PasswordInput
                  id="password"
                  required
                  tabIndex="0"
                  autoComplete="new-password"
                  name="password"
                  placeholder="Contraseña"
                  passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password_confirmation">
                  Confirmar contraseña
                </Label>
                <PasswordInput
                  id="password_confirmation"
                  required
                  tabIndex="0"
                  autoComplete="new-password"
                  name="password_confirmation"
                  placeholder="Confirmar contraseña"
                  passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
              </div>

              <Button
                type="submit"
                className="mt-2 w-full"
                tabIndex="0"
                data-test="register-user-button"
              >
                {processing && <Spinner />}
                Crear cuenta
              </Button>
            </div>

            <div className="text-center text-sm text-muted-foreground">
              ¿Ya tienes una cuenta?{' '}
              <TextLink href={login()} tabIndex="0">
                Iniciar sesión
              </TextLink>
            </div>
          </>
        )}
      </Form>
    </>
  );
}

Register.layout = {
  title: 'Crear una cuenta',
  description: 'Ingresa tus datos para crear tu cuenta',
};
