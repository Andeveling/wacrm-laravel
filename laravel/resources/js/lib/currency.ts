export const DEFAULT_CURRENCY = 'COP';

export interface CurrencyOption {
  /** ISO-4217 code, e.g. "COP". Stored verbatim in the DB. */
  code: string;
  /** Human label for the dropdown, e.g. "Peso colombiano". */
  label: string;
}

/** Codes must be valid ISO-4217 so Intl.NumberFormat renders the right symbol/grouping. */
export const CURRENCIES: CurrencyOption[] = [
  { code: 'COP', label: 'Peso colombiano' },
  { code: 'USD', label: 'Dólar estadounidense' },
  { code: 'EUR', label: 'Euro' },
  { code: 'GBP', label: 'Libra esterlina' },
  { code: 'MXN', label: 'Peso mexicano' },
  { code: 'BRL', label: 'Real brasileño' },
  { code: 'ARS', label: 'Peso argentino' },
  { code: 'CLP', label: 'Peso chileno' },
  { code: 'AUD', label: 'Dólar australiano' },
  { code: 'CAD', label: 'Dólar canadiense' },
  { code: 'INR', label: 'Rupia india' },
  { code: 'JPY', label: 'Yen japonés' },
  { code: 'CNY', label: 'Yuan chino' },
];

export function formatCurrency(
  value: number,
  currency: string = DEFAULT_CURRENCY,
): string {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value);
}

/** Compact form for tight spaces, e.g. "$1.2M" instead of "$1,200,000". */
export function formatCurrencyShort(
  value: number,
  currency: string = DEFAULT_CURRENCY,
): string {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency,
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(value);
}

/** Compact number for tight spaces (chart tiles, legends): 1_234 → "1.2k". */
export function formatCompactNumber(value: number): string {
  const v = Number(value || 0);
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `${(v / 1_000).toFixed(1)}k`;
  return v.toFixed(0);
}
