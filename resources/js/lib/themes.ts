export const THEME_IDS = [
  'violet',
  'emerald',
  'cobalt',
  'amber',
  'rose',
] as const;

export type ThemeId = (typeof THEME_IDS)[number];

export const DEFAULT_THEME: ThemeId = 'violet';
export const THEME_STORAGE_KEY = 'wacrm.theme';

export type ThemeMeta = {
  readonly id: ThemeId;
  readonly name: string;
  readonly swatch: string;
};

export const THEMES: readonly ThemeMeta[] = [
  { id: 'violet', name: 'Violet', swatch: 'oklch(0.526 0.247 293)' },
  { id: 'emerald', name: 'Emerald', swatch: 'oklch(0.62 0.16 162)' },
  { id: 'cobalt', name: 'Cobalt', swatch: 'oklch(0.585 0.2 254)' },
  { id: 'amber', name: 'Amber', swatch: 'oklch(0.745 0.16 65)' },
  { id: 'rose', name: 'Rose', swatch: 'oklch(0.645 0.22 16)' },
];

export function isThemeId(value: unknown): value is ThemeId {
  return typeof value === 'string' && THEME_IDS.includes(value as ThemeId);
}
