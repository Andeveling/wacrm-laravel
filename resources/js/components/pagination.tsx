import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/pagination';

type PaginationMeta = Pick<
  Paginated<unknown>,
  | 'current_page'
  | 'last_page'
  | 'total'
  | 'from'
  | 'to'
  | 'next_page_url'
  | 'prev_page_url'
>;

interface PaginationProps {
  meta: PaginationMeta;
  /** Prop keys to partially reload — keeps the navigation generic across pages. */
  only?: string[];
  previousTestId?: string;
  nextTestId?: string;
}

export function Pagination({
  meta,
  only,
  previousTestId = 'pagination-previous-page',
  nextTestId = 'pagination-next-page',
}: PaginationProps) {
  if (meta.last_page <= 1) return null;

  function visit(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true, only });
  }

  return (
    <div className="flex items-center justify-between">
      <p className="text-xs text-muted-foreground">
        Mostrando {meta.from ?? 0}–{meta.to ?? 0} de {meta.total}
      </p>
      <div className="flex items-center gap-1">
        <Button
          data-testid={previousTestId}
          variant="outline"
          size="icon"
          disabled={!meta.prev_page_url}
          onClick={() => visit(meta.prev_page_url)}
        >
          <ChevronLeft className="size-4" />
        </Button>
        <span className="px-2 text-xs text-muted-foreground">
          Página {meta.current_page} de {meta.last_page}
        </span>
        <Button
          data-testid={nextTestId}
          variant="outline"
          size="icon"
          disabled={!meta.next_page_url}
          onClick={() => visit(meta.next_page_url)}
        >
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </div>
  );
}
