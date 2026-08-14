import { Link } from '@inertiajs/react';
import { Fingerprint, Inbox, Moon, Sparkles, Sun } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useAppearance } from '@/hooks/use-appearance';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types/ui';

export default function AuthSimpleLayout({
  children,
  title,
  description,
}: AuthLayoutProps) {
  const { resolvedAppearance, updateAppearance } = useAppearance();
  const nextAppearance = resolvedAppearance === 'dark' ? 'light' : 'dark';
  const AppearanceIcon = nextAppearance === 'light' ? Sun : Moon;

  return (
    <div className="min-h-svh bg-background lg:grid lg:grid-cols-2">
      {/* Left panel: branding & visual (hidden on mobile) */}
      <div className="relative hidden overflow-hidden bg-muted/30 lg:flex lg:min-w-0 lg:items-center lg:justify-end lg:p-12 xl:p-24">
        {/* Abstract background / tonal gradients */}
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-[radial-gradient(50rem_50rem_at_10%_-10%,rgba(78,222,163,0.18),transparent_55%),radial-gradient(42rem_42rem_at_108%_112%,rgba(176,144,255,0.16),transparent_55%)]"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-t from-background via-background/75 to-transparent"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-r from-background/85 via-background/35 to-transparent"
        />

        {/* Foreground content */}
        <div className="relative z-10 w-full max-w-lg space-y-8">
          <div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1.5 backdrop-blur-sm">
            <Sparkles className="size-4 text-primary" />
            <span className="text-[11px] font-semibold tracking-[0.08em] text-primary uppercase">
              AI-First CRM
            </span>
          </div>

          <div className="space-y-4">
            <h1 className="text-3xl font-bold tracking-tight xl:text-4xl">
              Conecta con tus clientes en LATAM.
            </h1>
            <p className="max-w-md text-sm leading-relaxed text-muted-foreground">
              Centraliza tus conversaciones, automatiza respuestas con
              Inteligencia Artificial y acelera tus ventas directamente desde el
              ecosistema de WhatsApp.
            </p>
          </div>

          <div className="space-y-4 border-t border-border/60 pt-8">
            <div className="flex items-center gap-4 rounded-xl border border-border/50 bg-card/60 p-4 backdrop-blur-md">
              <div className="flex size-12 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                <Inbox className="size-5" />
              </div>
              <div>
                <h3 className="text-sm font-semibold">Inbox Unificado</h3>
                <p className="text-[13px] text-muted-foreground">
                  Todo tu equipo colaborando en un solo número.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Right panel: form */}
      <div className="relative z-20 flex min-w-0 flex-col justify-center bg-background px-6 py-12 sm:px-12 lg:border-l lg:border-border/60">
        <button
          type="button"
          className="absolute top-6 right-6 inline-flex size-9 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
          onClick={() => updateAppearance(nextAppearance)}
          aria-label={`Cambiar a modo ${nextAppearance === 'light' ? 'claro' : 'oscuro'}`}
          data-testid="auth-appearance-toggle"
        >
          <AppearanceIcon className="size-4" aria-hidden="true" />
        </button>
        <div
          className="auth-form-panel justify-center"
          data-testid="auth-form-panel"
        >
          <div className="mb-10 text-center">
            <div className="mb-6 flex items-center justify-center gap-2">
              <Link
                href={home()}
                className="flex items-center gap-2 font-medium"
              >
                <div className="flex size-10 items-center justify-center rounded-lg border border-primary/20 bg-linear-to-br from-primary/20 to-primary/5 text-primary">
                  <AppLogoIcon className="size-5 fill-current" />
                </div>
                <span className="text-lg font-bold tracking-tight">Wacrm</span>
              </Link>
            </div>
            <h2 className="mb-2 text-2xl font-semibold tracking-tight">
              {title}
            </h2>
            <p className="text-sm text-muted-foreground">{description}</p>
          </div>

          {children}
        </div>

        <div
          aria-hidden="true"
          className="pointer-events-none absolute right-4 bottom-4 hidden text-muted-foreground/30 lg:block"
        >
          <Fingerprint className="size-9" />
        </div>
      </div>
    </div>
  );
}
