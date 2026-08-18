import { Link } from '@inertiajs/react';
import {
  Coins,
  FileText,
  KeyRound,
  LayoutGrid,
  MessageSquare,
  Palette,
  ShieldCheck,
  Tags,
  User,
  Zap,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import settings from '@/routes/settings';
import type { NavItem } from '@/types/navigation';

const overviewNavItem: NavItem = {
  title: 'Resumen',
  href: settings.overview(),
  icon: LayoutGrid,
};

const navGroups: { label: string; items: NavItem[] }[] = [
  {
    label: 'Cuenta',
    items: [
      { title: 'Perfil', href: editProfile(), icon: User },
      { title: 'Seguridad', href: editSecurity(), icon: ShieldCheck },
      { title: 'Apariencia', href: editAppearance(), icon: Palette },
    ],
  },
  {
    label: 'Espacio de trabajo',
    items: [
      { title: 'WhatsApp', href: settings.whatsapp(), icon: Zap },
      {
        title: 'Plantillas',
        href: settings.templates(),
        icon: FileText,
      },
      {
        title: 'Respuestas rápidas',
        href: settings.quickReplies(),
        icon: MessageSquare,
      },
      {
        title: 'Campos personalizados',
        href: settings.fields(),
        icon: Tags,
      },
      { title: 'Pipelines', href: settings.deals(), icon: Coins },
      { title: 'API Keys', href: settings.apiKeys(), icon: KeyRound },
    ],
  },
];

function SettingsNavLink({ item, active }: { item: NavItem; active: boolean }) {
  const Icon = item.icon;

  return (
    <Link
      href={item.href}
      className={cn(
        'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground',
        active && 'bg-muted font-medium text-foreground',
      )}
    >
      {Icon ? <Icon className="h-4 w-4 shrink-0" /> : null}
      {item.title}
    </Link>
  );
}

export default function SettingsLayout({ children }: PropsWithChildren) {
  const { isCurrentUrl } = useCurrentUrl();

  return (
    <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
      <aside className="w-full lg:w-56 lg:shrink-0">
        <nav className="flex flex-col gap-4" aria-label="Settings">
          <SettingsNavLink
            item={overviewNavItem}
            active={isCurrentUrl(overviewNavItem.href)}
          />

          {navGroups.map((group) => (
            <div key={group.label} className="flex flex-col gap-1">
              <span className="px-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {group.label}
              </span>
              {group.items.map((item) => (
                <SettingsNavLink
                  key={item.title}
                  item={item}
                  active={isCurrentUrl(item.href)}
                />
              ))}
            </div>
          ))}
        </nav>
      </aside>

      <Separator className="lg:hidden" />

      <div className="min-w-0 flex-1">{children}</div>
    </div>
  );
}
