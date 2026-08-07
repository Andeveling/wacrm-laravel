import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types/pagination';

export const PER_PAGE_OPTIONS = [10, 25, 50, 100];

type PaginationMeta = Pick<
  Paginated<unknown>,
  | 'current_page'
  | 'last_page'
  | 'per_page'
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
  perPageTestId?: string;
}

export function Pagination({
  meta,
  only,
  previousTestId = 'pagination-previous-page',
  nextTestId = 'pagination-next-page',
  perPageTestId = 'pagination-per-page',
}: PaginationProps) {
  if (meta.total === 0) return null;

  function visit(url: string | null) {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true, only });
  }

  function changePerPage(perPage: string) {
    router.reload({
      data: { per_page: perPage, page: 1 },
      replace: true,
      only,
    });
  }

  return (
    <div className="flex flex-wrap items-center justify-between gap-2">
      <div className="flex items-center gap-2">
        <p className="text-xs text-muted-foreground">
          Mostrando {meta.from ?? 0}–{meta.to ?? 0} de {meta.total}
        </p>
        <Select value={String(meta.per_page)} onValueChange={changePerPage}>
          <SelectTrigger
            data-testid={perPageTestId}
            size="sm"
            aria-label="Filas por página"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent side="top">
            {PER_PAGE_OPTIONS.map((option) => (
              <SelectItem key={option} value={String(option)}>
                {option} por página
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
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
