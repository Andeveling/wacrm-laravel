import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { WhatsappActivationEmpty } from '@/components/whatsapp-activation-empty';
import { isProductPage } from '@/lib/whatsapp-activation';
import type { AppLayoutProps } from '@/types/ui';

export default function AppSidebarLayout({
  children,
  breadcrumbs = [],
}: AppLayoutProps) {
  const { component, props } = usePage();
  const showActivationEmpty =
    !props.hasWhatsappConnection && isProductPage(component);
  const content = showActivationEmpty ? <WhatsappActivationEmpty /> : children;

  return (
    <AppShell variant="sidebar">
      <AppSidebar />
      <AppContent
        variant="sidebar"
        className="overflow-x-hidden"
        header={<AppSidebarHeader breadcrumbs={breadcrumbs} />}
      >
        {content}
      </AppContent>
    </AppShell>
  );
}
