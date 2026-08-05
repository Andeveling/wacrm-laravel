import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
  recoveryCodesList: string[];
  fetchRecoveryCodes: () => Promise<void>;
  errors: string[];
};

export default function TwoFactorRecoveryCodes({
  recoveryCodesList,
  fetchRecoveryCodes,
  errors,
}: Props) {
  const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
  const codesSectionRef = useRef<HTMLDivElement | null>(null);
  const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

  const toggleCodesVisibility = useCallback(async () => {
    if (!codesAreVisible && !recoveryCodesList.length) {
      await fetchRecoveryCodes();
    }

    setCodesAreVisible(!codesAreVisible);

    if (!codesAreVisible) {
      setTimeout(() => {
        codesSectionRef.current?.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest',
        });
      });
    }
  }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

  useEffect(() => {
    if (!recoveryCodesList.length) {
      fetchRecoveryCodes();
    }
  }, [recoveryCodesList.length, fetchRecoveryCodes]);

  const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex gap-3">
          <LockKeyhole className="size-4" aria-hidden="true" />
          Códigos de recuperación 2FA
        </CardTitle>
        <CardDescription>
          Los códigos de recuperación te permiten volver a acceder si pierdes tu
          dispositivo 2FA. Guárdalos en un administrador de contraseñas seguro.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
          <Button
            onClick={toggleCodesVisibility}
            className="w-fit"
            aria-expanded={codesAreVisible}
            aria-controls="recovery-codes-section"
          >
            <RecoveryCodeIconComponent className="size-4" aria-hidden="true" />
            {codesAreVisible ? 'Ocultar' : 'Ver'} códigos de recuperación
          </Button>

          {canRegenerateCodes && (
            <Form
              {...regenerateRecoveryCodes.form()}
              options={{ preserveScroll: true }}
              onSuccess={fetchRecoveryCodes}
            >
              {({ processing }) => (
                <Button
                  variant="secondary"
                  type="submit"
                  disabled={processing}
                  aria-describedby="regenerate-warning"
                >
                  <RefreshCw /> Regenerar códigos
                </Button>
              )}
            </Form>
          )}
        </div>
        <div
          id="recovery-codes-section"
          className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
          aria-hidden={!codesAreVisible}
        >
          <div className="mt-3 space-y-3">
            {errors?.length ? (
              <AlertError errors={errors} />
            ) : (
              <>
                <div
                  ref={codesSectionRef}
                  className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                  role="list"
                  aria-label="Códigos de recuperación"
                >
                  {recoveryCodesList.length ? (
                    recoveryCodesList.map((code, index) => (
                      <div key={index} role="listitem" className="select-text">
                        {code}
                      </div>
                    ))
                  ) : (
                    <div
                      className="space-y-2"
                      aria-label="Cargando códigos de recuperación"
                    >
                      {Array.from({ length: 8 }, (_, index) => (
                        <div
                          key={index}
                          className="h-4 animate-pulse rounded bg-muted-foreground/20"
                          aria-hidden="true"
                        />
                      ))}
                    </div>
                  )}
                </div>

                <div className="text-xs text-muted-foreground select-none">
                  <p id="regenerate-warning">
                    Cada código de recuperación se puede usar una vez para
                    acceder a tu cuenta y se eliminará después de usarlo. Si
                    necesitas más, haz clic en{' '}
                    <span className="font-bold">Regenerar códigos</span> arriba.
                  </p>
                </div>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
