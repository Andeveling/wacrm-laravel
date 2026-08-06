import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import Heading from '@/components/heading';
import type { Passkey } from '../contracts';
import PasskeyItem from './passkey-item';
import PasskeyRegistration from './passkey-register';

export type Props = {
  canManagePasskeys?: boolean;
  passkeys?: Passkey[];
};

const EmptyState = () => {
  return (
    <div className="p-8 text-center">
      <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
        <KeyRound className="h-7 w-7 text-muted-foreground" />
      </div>
      <p className="font-medium">Aún no tienes llaves de acceso</p>
      <p className="mt-1 text-sm text-muted-foreground">
        Agrega una llave de acceso para iniciar sesión sin contraseña
      </p>
    </div>
  );
};

export default function ManagePasskeys(props: Props) {
  const passkeys = props.passkeys ?? [];

  const handleDelete = (id: number, onError: () => void) => {
    router.delete(destroy.url(id), {
      preserveScroll: true,
      onError,
    });
  };

  const handleRegisterSuccess = () => {
    router.reload();
  };

  if (!(props.canManagePasskeys ?? false)) {
    return null;
  }

  return (
    <div className="space-y-6">
      <Heading
        variant="small"
        title="Llaves de acceso"
        description="Gestiona tus llaves de acceso para iniciar sesión sin contraseña"
      />

      <div className="overflow-hidden rounded-lg border border-border">
        {passkeys.length > 0 ? (
          passkeys.map((passkey) => (
            <PasskeyItem
              key={passkey.id}
              passkey={passkey}
              onDelete={handleDelete}
            />
          ))
        ) : (
          <EmptyState />
        )}
      </div>

      <PasskeyRegistration onSuccess={handleRegisterSuccess} />
    </div>
  );
}
