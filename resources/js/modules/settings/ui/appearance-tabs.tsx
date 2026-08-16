import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Palette, Sun } from 'lucide-react';
import { type HTMLAttributes, useId } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { THEMES, type ThemeId } from '@/lib/themes';
import { cn } from '@/lib/utils';

type ModeOption = {
  readonly value: Appearance;
  readonly icon: LucideIcon;
  readonly label: string;
};

const MODES: readonly ModeOption[] = [
  { value: 'light', icon: Sun, label: 'Light' },
  { value: 'dark', icon: Moon, label: 'Dark' },
  { value: 'system', icon: Monitor, label: 'System' },
];

export default function AppearanceToggleTab({
  className = '',
  ...props
}: HTMLAttributes<HTMLDivElement>) {
  const { appearance, updateAppearance, theme, updateTheme } = useAppearance();
  const modeHeadingId = useId();
  const themeHeadingId = useId();

  return (
    <div className={cn('space-y-8', className)} {...props}>
      <section className="space-y-3" aria-labelledby={modeHeadingId}>
        <h2 id={modeHeadingId} className="text-sm font-medium">
          Color mode
        </h2>
        <div
          className="grid max-w-lg grid-cols-1 gap-2 sm:grid-cols-3"
          role="radiogroup"
          aria-labelledby={modeHeadingId}
        >
          {MODES.map(({ value, icon: Icon, label }) => (
            <button
              type="button"
              key={value}
              role="radio"
              aria-checked={appearance === value}
              aria-label={`Use ${label.toLowerCase()} mode`}
              data-testid={`appearance-mode-${value}`}
              onClick={() => updateAppearance(value)}
              className={cn(
                'flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring',
                appearance === value
                  ? 'border-primary bg-primary-soft text-foreground'
                  : 'border-border text-muted-foreground hover:bg-muted',
              )}
            >
              <Icon className="size-4" aria-hidden="true" />
              {label}
            </button>
          ))}
        </div>
      </section>

      <section className="space-y-3" aria-labelledby={themeHeadingId}>
        <h2
          id={themeHeadingId}
          className="flex items-center gap-2 text-sm font-medium"
        >
          <Palette
            className="size-4 text-muted-foreground"
            aria-hidden="true"
          />
          Accent color
        </h2>
        <div
          className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5"
          role="radiogroup"
          aria-labelledby={themeHeadingId}
        >
          {THEMES.map((themeOption) => (
            <ThemeButton
              key={themeOption.id}
              theme={themeOption.id}
              name={themeOption.name}
              swatch={themeOption.swatch}
              active={theme === themeOption.id}
              onPick={() => updateTheme(themeOption.id)}
            />
          ))}
        </div>
      </section>
    </div>
  );
}

function ThemeButton({
  theme,
  name,
  swatch,
  active,
  onPick,
}: {
  readonly theme: ThemeId;
  readonly name: string;
  readonly swatch: string;
  readonly active: boolean;
  readonly onPick: () => void;
}) {
  return (
    <button
      type="button"
      role="radio"
      aria-checked={active}
      aria-label={`Use ${name} theme`}
      data-testid={`appearance-theme-${theme}`}
      onClick={onPick}
      className={cn(
        'flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring',
        active
          ? 'border-primary bg-primary-soft text-foreground'
          : 'border-border text-muted-foreground hover:bg-muted',
      )}
    >
      <span
        className="size-4 rounded-full ring-1 ring-black/10"
        style={{ backgroundColor: swatch }}
        aria-hidden="true"
      />
      <span className="flex-1 text-left">{name}</span>
      {active ? (
        <Check className="size-4 text-primary" aria-hidden="true" />
      ) : null}
    </button>
  );
}
