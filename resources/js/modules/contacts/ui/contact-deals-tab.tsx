import { router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/currency';
import { pipelines } from '@/routes';
import type { ContactDeal, DealStatus } from '../contracts';

const DEAL_STATUS_LABEL: Record<DealStatus, string> = {
  open: 'Abierto',
  won: 'Ganado',
  lost: 'Perdido',
};

interface ContactDealsTabProps {
  deals: ContactDeal[] | undefined;
}

export function ContactDealsTab({ deals }: ContactDealsTabProps) {
  if (deals === undefined) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-16 w-full" />
        <Skeleton className="h-16 w-full" />
      </div>
    );
  }

  if (deals.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">Sin negocios registrados.</p>
    );
  }

  return (
    <div className="space-y-3">
      <ul className="space-y-2">
        {deals.map((deal) => (
          <li key={deal.id} className="rounded-lg border p-3 text-sm">
            <div className="flex items-start justify-between gap-2">
              <span className="font-medium">{deal.title}</span>
              <span className="text-xs text-muted-foreground">
                {deal.status ? DEAL_STATUS_LABEL[deal.status] : null}
              </span>
            </div>
            <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
              <span>{deal.stage?.name ?? 'Sin etapa'}</span>
              <span className="font-semibold text-primary">
                {formatCurrency(Number(deal.value), deal.currency ?? undefined)}
              </span>
            </div>
          </li>
        ))}
      </ul>
      <Button
        variant="outline"
        size="sm"
        onClick={() => router.visit(pipelines().url)}
      >
        <ExternalLink className="size-4" />
        Ver en Pipelines
      </Button>
    </div>
  );
}
