import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import { OTP_MAX_LENGTH } from '../use-two-factor-auth';

export default function TwoFactorChallenge() {
  const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
  const [code, setCode] = useState<string>('');

  const authConfigContent = useMemo<{
    title: string;
    description: string;
    toggleText: string;
  }>(() => {
    if (showRecoveryInput) {
      return {
        title: 'Código de recuperación',
        description:
          'Confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia.',
        toggleText: 'iniciar sesión con un código de autenticación',
      };
    }

    return {
      title: 'Código de autenticación',
      description:
        'Ingresa el código de autenticación proporcionado por tu aplicación autenticadora.',
      toggleText: 'iniciar sesión con un código de recuperación',
    };
  }, [showRecoveryInput]);

  setLayoutProps({
    title: authConfigContent.title,
    description: authConfigContent.description,
  });

  const toggleRecoveryMode = (clearErrors: () => void): void => {
    setShowRecoveryInput(!showRecoveryInput);
    clearErrors();
    setCode('');
  };

  return (
    <>
      <Head title="Autenticación en dos pasos" />

      <div className="space-y-6">
        <Form
          {...store.form()}
          className="space-y-4"
          resetOnError
          resetOnSuccess={!showRecoveryInput}
        >
          {({ errors, processing, clearErrors }) => (
            <>
              {showRecoveryInput ? (
                <>
                  <Input
                    name="recovery_code"
                    type="text"
                    placeholder="Ingresa el código de recuperación"
                    autoFocus={showRecoveryInput}
                    required
                  />
                  <InputError message={errors.recovery_code} />
                </>
              ) : (
                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                  <div className="flex w-full items-center justify-center">
                    <InputOTP
                      name="code"
                      maxLength={OTP_MAX_LENGTH}
                      value={code}
                      onChange={(value) => setCode(value)}
                      disabled={processing}
                      pattern={REGEXP_ONLY_DIGITS}
                      autoFocus
                    >
                      <InputOTPGroup>
                        {Array.from({ length: OTP_MAX_LENGTH }, (_, index) => (
                          <InputOTPSlot
                            key={index}
                            index={index}
                            className="size-12 text-base"
                            data-testid="two-factor-otp-slot"
                          />
                        ))}
                      </InputOTPGroup>
                    </InputOTP>
                  </div>
                  <InputError message={errors.code} />
                </div>
              )}

              <Button type="submit" className="w-full" disabled={processing}>
                Continuar
              </Button>

              <div className="text-center text-sm text-muted-foreground">
                <span>o puedes </span>
                <button
                  type="button"
                  className="h-12 cursor-pointer text-base text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                  onClick={() => toggleRecoveryMode(clearErrors)}
                  data-testid="two-factor-recovery-toggle"
                >
                  {authConfigContent.toggleText}
                </button>
              </div>
            </>
          )}
        </Form>
      </div>
    </>
  );
}
