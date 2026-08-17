import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

if (import.meta.env.DEV) {
  import('react-grab');
}

const appName = 'Wacrm';

// Apply persisted mode and accent before Inertia starts its first render.
initializeTheme();

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name.startsWith('auth/'):
        return AuthLayout;
      case name.startsWith('settings/'):
        return [AppLayout, SettingsLayout];
      default:
        return AppLayout;
    }
  },
  strictMode: true,
  withApp(app) {
    return (
      <TooltipProvider delay={0}>
        {app}
        <Toaster />
      </TooltipProvider>
    );
  },
  progress: {
    color: '#4B5563',
  },
});

// The boot state is ready before React hydrates, preventing a theme flash.
