export function startOfLocalDay(d: Date = new Date()): Date {
  const out = new Date(d);
  out.setHours(0, 0, 0, 0);
  return out;
}

export function daysAgoStart(days: number): Date {
  const out = startOfLocalDay();
  out.setDate(out.getDate() - days);
  return out;
}

/** Date-only key (YYYY-MM-DD) for bucketing rows by local calendar day. */
export function localDayKey(d: Date | string): string {
  const date = typeof d === 'string' ? new Date(d) : d;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/** Inclusive list of local-day keys spanning the last `n` days, in chronological order. */
export function lastNDayKeys(n: number): string[] {
  const keys: string[] = [];
  const start = daysAgoStart(n - 1);
  for (let i = 0; i < n; i++) {
    const d = new Date(start);
    d.setDate(d.getDate() + i);
    keys.push(localDayKey(d));
  }
  return keys;
}

export const DOW_SHORT_MON_FIRST = [
  'Lun',
  'Mar',
  'Mié',
  'Jue',
  'Vie',
  'Sáb',
  'Dom',
] as const;
