import { CheckCircle2, ShieldCheck, Star, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { needsAttention, STEP_LABELS } from '../model';
import type { WhatsappConnection } from '../types';

export function ConnectionHeader({
  connection,
  testId,
}: {
  connection: WhatsappConnection;
  testId: string;
}) {
  const attention = needsAttention(connection.readiness);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div className="min-w-0">
        <p className="font-medium text-foreground">
          {connection.phone_number_id ?? 'Número pendiente'}
        </p>
        <p className="text-xs text-muted-foreground">
          WABA: {connection.waba_id ?? 'pendiente de validación'}
        </p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {connection.is_default ? (
          <Badge variant="outline">
            <Star className="size-3" />
            Predeterminado
          </Badge>
        ) : null}
        <Badge
          data-testid={`whatsapp-readiness-${testId}`}
          variant={
            attention
              ? 'destructive'
              : connection.readiness === 'active'
                ? 'default'
                : 'secondary'
          }
        >
          {connection.readiness === 'active' ? (
            <CheckCircle2 className="size-3" />
          ) : attention ? (
            <XCircle className="size-3" />
          ) : (
            <ShieldCheck className="size-3" />
          )}
          {STEP_LABELS[connection.readiness]}
        </Badge>
      </div>
    </div>
  );
}
