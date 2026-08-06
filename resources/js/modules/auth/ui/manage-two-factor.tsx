import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { disable, enable } from '@/routes/two-factor';
import { useTwoFactorAuth } from '../use-two-factor-auth';
import TwoFactorRecoveryCodes from './two-factor-recovery-codes';
import TwoFactorSetupModal from './two-factor-setup-modal';

export type Props = {
  canManageTwoFactor?: boolean;
  requiresConfirmation?: boolean;
  twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
  const requiresConfirmation = props.requiresConfirmation ?? false;
  const twoFactorEnabled = props.twoFactorEnabled ?? false;

  const {
    qrCodeSvg,
    hasSetupData,
    manualSetupKey,
    clearSetupData,
    clearTwoFactorAuthData,
    fetchSetupData,
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
  } = useTwoFactorAuth();
  const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
  const prevTwoFactorEnabled = useRef(twoFactorEnabled);

  useEffect(() => {
    if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
      clearTwoFactorAuthData();
    }

    prevTwoFactorEnabled.current = twoFactorEnabled;
  }, [twoFactorEnabled, clearTwoFactorAuthData]);

  if (!(props.canManageTwoFactor ?? false)) {
    return null;
  }

  return (
    <div className="space-y-6">
      <Heading
        variant="small"
        title="Autenticación en dos pasos"
        description="Gestiona la configuración de autenticación en dos pasos"
      />
      {twoFactorEnabled ? (
        <div className="flex flex-col items-start justify-start space-y-4">
          <p className="text-sm text-muted-foreground">
            Se te solicitará un pin aleatorio y seguro al iniciar sesión, que
            puedes obtener desde la aplicación compatible con TOTP en tu
            teléfono.
          </p>

          <div className="relative inline">
            <Form {...disable.form()}>
              {({ processing }) => (
                <Button
                  variant="destructive"
                  type="submit"
                  disabled={processing}
                >
                  Desactivar 2FA
                </Button>
              )}
            </Form>
          </div>

          <TwoFactorRecoveryCodes
            recoveryCodesList={recoveryCodesList}
            fetchRecoveryCodes={fetchRecoveryCodes}
            errors={errors}
          />
        </div>
      ) : (
        <div className="flex flex-col items-start justify-start space-y-4">
          <p className="text-sm text-muted-foreground">
            Al activar la autenticación en dos pasos, se te solicitará un pin
            seguro al iniciar sesión. Este pin se puede obtener desde una
            aplicación compatible con TOTP en tu teléfono.
          </p>

          <div>
            {hasSetupData ? (
              <Button onClick={() => setShowSetupModal(true)}>
                <ShieldCheck />
                Continuar configuración
              </Button>
            ) : (
              <Form
                {...enable.form()}
                onSuccess={() => setShowSetupModal(true)}
              >
                {({ processing }) => (
                  <Button type="submit" disabled={processing}>
                    Activar 2FA
                  </Button>
                )}
              </Form>
            )}
          </div>
        </div>
      )}

      <TwoFactorSetupModal
        isOpen={showSetupModal}
        onClose={() => setShowSetupModal(false)}
        requiresConfirmation={requiresConfirmation}
        twoFactorEnabled={twoFactorEnabled}
        qrCodeSvg={qrCodeSvg}
        manualSetupKey={manualSetupKey}
        clearSetupData={clearSetupData}
        fetchSetupData={fetchSetupData}
        errors={errors}
      />
    </div>
  );
}
