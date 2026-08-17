import { Link, usePage } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty';
import { canConnectWhatsapp } from '@/lib/whatsapp-activation';
import settings from '@/routes/settings';

export function WhatsappActivationEmpty() {
  const { currentAccount } = usePage().props;
  const canConnect = canConnectWhatsapp(currentAccount?.role);

  return (
    <Empty>
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Smartphone />
        </EmptyMedia>
        <EmptyTitle>Conectá WhatsApp para empezar</EmptyTitle>
        <EmptyDescription>
          {canConnect
            ? 'El siguiente paso es conectar un número de WhatsApp Business.'
            : 'Pedile a un admin que conecte un número'}
        </EmptyDescription>
      </EmptyHeader>
      {canConnect ? (
        <EmptyContent>
          <Button asChild>
            <Link href={settings.whatsapp()}>
              Conectá tu primer número de WhatsApp
            </Link>
          </Button>
        </EmptyContent>
      ) : null}
    </Empty>
  );
}
