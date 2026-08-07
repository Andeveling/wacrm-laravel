export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

/** Mirrors `LengthAwarePaginator::toArray()` — Laravel's native paginate() shape. */
export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  next_page_url: string | null;
  prev_page_url: string | null;
  links: PaginationLink[];
}
