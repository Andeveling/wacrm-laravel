import { useEffect, useSyncExternalStore } from 'react';
import {
  DEFAULT_THEME,
  isThemeId,
  THEME_STORAGE_KEY,
  type ThemeId,
} from '@/lib/themes';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
  readonly appearance: Appearance;
  readonly resolvedAppearance: ResolvedAppearance;
  readonly theme: ThemeId;
  readonly updateAppearance: (mode: Appearance) => void;
  readonly updateTheme: (theme: ThemeId) => void;
};

const listeners = new Set<() => void>();
const DEFAULT_APPEARANCE: Appearance = 'dark';
const APPEARANCE_STORAGE_KEY = 'appearance';
let currentAppearance: Appearance = DEFAULT_APPEARANCE;
let currentTheme: ThemeId = DEFAULT_THEME;
let systemMediaQuery: MediaQueryList | null = null;

const prefersDark = (): boolean => {
  if (typeof window === 'undefined') {
    return true;
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const setCookie = (name: string, value: string, days = 365): void => {
  if (typeof document === 'undefined') {
    return;
  }

  const maxAge = days * 24 * 60 * 60;
  // biome-ignore lint/suspicious/noDocumentCookie: Cookie Store is not supported by every target browser.
  document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAppearance = (): Appearance => {
  if (typeof window === 'undefined') {
    return DEFAULT_APPEARANCE;
  }

  try {
    const stored = localStorage.getItem(APPEARANCE_STORAGE_KEY);
    if (stored === 'light' || stored === 'dark' || stored === 'system') {
      return stored;
    }
  } catch {
    // Fall back to the legacy cookie when localStorage is unavailable.
  }

  const cookieAppearance = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith(`${APPEARANCE_STORAGE_KEY}=`))
    ?.split('=')[1];

  return cookieAppearance === 'light' ||
    cookieAppearance === 'dark' ||
    cookieAppearance === 'system'
    ? cookieAppearance
    : DEFAULT_APPEARANCE;
};

const getStoredTheme = (): ThemeId => {
  if (typeof window === 'undefined') {
    return DEFAULT_THEME;
  }

  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    return isThemeId(stored) ? stored : DEFAULT_THEME;
  } catch {
    return DEFAULT_THEME;
  }
};

const resolveAppearance = (appearance: Appearance): ResolvedAppearance =>
  appearance === 'system' ? (prefersDark() ? 'dark' : 'light') : appearance;

const applyTheme = (appearance: Appearance, theme: ThemeId): void => {
  if (typeof document === 'undefined') {
    return;
  }

  const resolvedAppearance = resolveAppearance(appearance);
  document.documentElement.dataset.mode = resolvedAppearance;
  document.documentElement.dataset.theme = theme;
  document.documentElement.style.colorScheme = resolvedAppearance;
};

const subscribe = (callback: () => void): (() => void) => {
  listeners.add(callback);
  return () => listeners.delete(callback);
};

const notify = (): void => {
  listeners.forEach((listener) => {
    listener();
  });
};

const handleSystemThemeChange = (): void => {
  if (currentAppearance === 'system') {
    applyTheme(currentAppearance, currentTheme);
    notify();
  }
};

export function initializeTheme(): void {
  if (typeof window === 'undefined') {
    return;
  }

  currentAppearance = getStoredAppearance();
  currentTheme = getStoredTheme();
  applyTheme(currentAppearance, currentTheme);

  systemMediaQuery?.removeEventListener('change', handleSystemThemeChange);
  systemMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
  systemMediaQuery.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance(): UseAppearanceReturn {
  const appearance = useSyncExternalStore(
    subscribe,
    () => currentAppearance,
    () => DEFAULT_APPEARANCE,
  );
  const theme = useSyncExternalStore(
    subscribe,
    () => currentTheme,
    () => DEFAULT_THEME,
  );

  useEffect(() => {
    const handleStorage = (event: StorageEvent): void => {
      if (event.key === APPEARANCE_STORAGE_KEY) {
        if (
          event.newValue === 'light' ||
          event.newValue === 'dark' ||
          event.newValue === 'system'
        ) {
          currentAppearance = event.newValue;
          applyTheme(currentAppearance, currentTheme);
          notify();
        }
        return;
      }

      if (event.key === THEME_STORAGE_KEY && isThemeId(event.newValue)) {
        currentTheme = event.newValue;
        applyTheme(currentAppearance, currentTheme);
        notify();
      }
    };

    window.addEventListener('storage', handleStorage);
    return () => window.removeEventListener('storage', handleStorage);
  }, []);

  const updateAppearance = (mode: Appearance): void => {
    currentAppearance = mode;
    applyTheme(currentAppearance, currentTheme);
    try {
      localStorage.setItem(APPEARANCE_STORAGE_KEY, mode);
    } catch {
      // The current tab still works when storage is unavailable.
    }
    setCookie(APPEARANCE_STORAGE_KEY, mode);
    notify();
  };

  const updateTheme = (nextTheme: ThemeId): void => {
    currentTheme = nextTheme;
    applyTheme(currentAppearance, currentTheme);
    try {
      localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
    } catch {
      // The current tab still works when storage is unavailable.
    }
    notify();
  };

  return {
    appearance,
    resolvedAppearance: resolveAppearance(appearance),
    theme,
    updateAppearance,
    updateTheme,
  } as const;
}
