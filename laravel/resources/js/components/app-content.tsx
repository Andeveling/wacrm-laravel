import type * as React from 'react';
import type { ReactNode } from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = React.ComponentProps<'main'> & {
  variant?: AppVariant;
  header?: ReactNode;
};

export function AppContent({
  variant = 'sidebar',
  header,
  children,
  ...props
}: Props) {
  if (variant === 'sidebar') {
    return (
      <SidebarInset {...props}>
        {header}
        <div className="flex flex-1 flex-col p-4 md:p-6">{children}</div>
      </SidebarInset>
    );
  }

  return (
    <main
      className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl p-12"
      {...props}
    >
      <div className="p-6">{children}</div>
    </main>
  );
}
