import { Calendar, Check, UserRound, X } from 'lucide-react';
import { formatCurrency } from '@/lib/currency';
import type { Deal, PipelineStage } from '../contracts';

interface DealCardProps {
  deal: Deal;
  stage: PipelineStage | null;
  onEdit: (deal: Deal) => void;
  isOverlay?: boolean;
}

function formatDate(dateStr: string) {
  return new Date(dateStr).toLocaleDateString('es-CO', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function initials(name?: string | null, fallback?: string | null) {
  const source = (name || fallback || '?').trim();
  if (!source) return '?';
  return source.charAt(0).toUpperCase();
}

export function DealCard({ deal, stage, onEdit, isOverlay }: DealCardProps) {
  const contactLabel =
    deal.contact?.name || deal.contact?.phone || 'Sin contacto';

  return (
    <button
      type="button"
      onClick={(e) => {
        if (isOverlay) return;
        e.stopPropagation();
        onEdit(deal);
      }}
      className={`group relative w-full cursor-pointer rounded-xl border border-border/50 bg-muted/70 py-3 pr-3 pl-4 text-left shadow-sm transition-all ${
        isOverlay
          ? 'shadow-xl'
          : 'hover:-translate-y-0.5 hover:border-border hover:bg-muted hover:shadow-lg'
      }`}
    >
      <span
        aria-hidden
        className="absolute top-0 left-0 h-full w-1 rounded-l-xl"
        style={{ backgroundColor: stage?.color ?? '#94a3b8' }}
      />

      <div className="flex items-start justify-between gap-2">
        <h4 className="flex-1 text-sm leading-snug font-semibold break-words text-foreground">
          {deal.title}
        </h4>
        {deal.status === 'won' && (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-semibold text-primary">
            <Check className="h-3 w-3" />
            Ganado
          </span>
        )}
        {deal.status === 'lost' && (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-500/15 px-2 py-0.5 text-[10px] font-semibold text-red-400">
            <X className="h-3 w-3" />
            Perdido
          </span>
        )}
      </div>

      <div className="mt-2 flex items-center gap-2">
        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-foreground">
          {initials(deal.contact?.name, deal.contact?.phone)}
        </span>
        <span className="truncate text-xs text-muted-foreground">
          {contactLabel}
        </span>
      </div>

      <div className="mt-2 flex items-center justify-between">
        <span className="text-sm font-bold text-primary">
          {formatCurrency(Number(deal.value), deal.currency)}
        </span>
        {deal.expected_close_date && (
          <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Calendar className="h-3 w-3" />
            {formatDate(deal.expected_close_date)}
          </span>
        )}
      </div>

      <div className="mt-2 flex items-center gap-1 text-[11px] text-muted-foreground">
        <UserRound className="h-3 w-3" />
        <span className="truncate">{deal.assignee?.name ?? 'Sin asignar'}</span>
      </div>
    </button>
  );
}
