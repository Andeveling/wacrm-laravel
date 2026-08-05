import { Link } from '@inertiajs/react';
import {
  Bell,
  Bot,
  GitBranch,
  Inbox,
  LayoutGrid,
  Radio,
  Settings,
  Users,
  Workflow,
  Zap,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import settings from '@/routes/settings';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: 'Inbox',
    href: '/inbox',
    icon: Inbox,
  },
  {
    title: 'Contactos',
    href: '/contacts',
    icon: Users,
  },
  {
    title: 'Pipelines',
    href: '/pipelines',
    icon: GitBranch,
  },
  {
    title: 'Difusiones',
    href: '/broadcasts',
    icon: Radio,
  },
  {
    title: 'Automatizaciones',
    href: '/automations',
    icon: Zap,
  },
  {
    title: 'Flujos',
    href: '/flows',
    icon: Workflow,
  },
  {
    title: 'Agentes de IA',
    href: '/agents',
    icon: Bot,
  },
  {
    title: 'Notificaciones',
    href: '/notifications',
    icon: Bell,
  },
  {
    title: 'Configuración',
    href: settings.overview(),
    icon: Settings,
  },
];

export function AppSidebar() {
  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href={dashboard()} prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <NavMain items={mainNavItems} />
      </SidebarContent>

      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
