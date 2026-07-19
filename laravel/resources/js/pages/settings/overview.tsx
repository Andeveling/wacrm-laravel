import { Head, Link, usePage } from '@inertiajs/react';
import {
  Coins,
  FileText,
  KeyRound,
  type LucideIcon,
  MessageSquare,
  Palette,
  ShieldCheck,
  Tags,
  User as UserIcon,
  Users,
  Zap,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { UserInfo } from '@/components/user-info';

type PanelStatus = 'Disponible' | 'Disponible con módulo' | 'Próximamente';

type Panel = {
  slug: string;
  title: string;
  description: string;
  status: PanelStatus;
  href: string | null;
};

type PageProps = {
  panels: Panel[];
};

const statusBadgeClass: Record<PanelStatus, string> = {
  Disponible: 'bg-emerald-100 text-emerald-800 border-transparent',
  'Disponible con módulo': 'bg-amber-100 text-amber-800 border-transparent',
  Próximamente: 'bg-zinc-200 text-zinc-700 border-transparent',
};

const panelIcons: Record<string, LucideIcon> = {
  profile: UserIcon,
  security: ShieldCheck,
  appearance: Palette,
  members: Users,
  'api-keys': KeyRound,
  whatsapp: Zap,
  templates: FileText,
  'quick-replies': MessageSquare,
  fields: Tags,
  deals: Coins,
};

export default function Overview({ panels }: PageProps) {
  const { auth } = usePage().props;

  return (
    <>
      <Head title="Configuración" />

      <div className="space-y-6">
        <Heading
          title="Configuración"
          description="Todo en un solo lugar — tu cuenta y tu espacio de trabajo."
        />

        <Card className="flex-row items-center px-6 py-4">
          <UserInfo user={auth.user} showEmail />
        </Card>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {panels.map((panel) => {
            const Icon = panelIcons[panel.slug];

            const body = (
              <Card className="h-full">
                <CardHeader>
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      {Icon && (
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                          <Icon className="size-4" />
                        </span>
                      )}
                      <CardTitle>{panel.title}</CardTitle>
                    </div>
                    <Badge className={statusBadgeClass[panel.status]}>
                      {panel.status}
                    </Badge>
                  </div>
                  <CardDescription>{panel.description}</CardDescription>
                </CardHeader>
              </Card>
            );

            if (!panel.href) {
              return <div key={panel.slug}>{body}</div>;
            }

            return (
              <Link
                key={panel.slug}
                href={panel.href}
                className="block rounded-xl transition hover:ring-2 hover:ring-foreground/20"
              >
                {body}
              </Link>
            );
          })}
        </div>
      </div>
    </>
  );
}

Overview.layout = {
  breadcrumbs: [
    {
      title: 'Settings',
      href: '/settings',
    },
  ],
};
