import { usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useInitials } from '@/hooks/use-initials';
import { accountTypeLabel } from '@/lib/account-type-label';
import type { CurrentAccount } from '@/types/auth';

function AccountTile({
  account,
  getInitials,
}: {
  account: CurrentAccount;
  getInitials: (name: string) => string;
}) {
  return (
    <>
      <Avatar className="size-8 rounded-lg">
        <AvatarFallback className="rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
          {getInitials(account.name)}
        </AvatarFallback>
      </Avatar>
      <div className="grid flex-1 text-left text-sm leading-tight">
        <span className="truncate font-medium">{account.name}</span>
        <span className="truncate text-xs">
          {accountTypeLabel(account.type)}
        </span>
      </div>
    </>
  );
}

export function AccountSwitcher() {
  const { currentAccount: account } = usePage().props;
  const getInitials = useInitials();

  if (account === null) {
    return (
      <SidebarMenu>
        <SidebarMenuItem>
          <div
            className="flex flex-col gap-1 px-2 py-1.5 text-xs text-muted-foreground"
            data-testid="accounts-switcher"
          >
            <span className="font-medium text-sidebar-foreground">
              Sin cuenta
            </span>
            <span>No perteneces a ninguna cuenta</span>
          </div>
        </SidebarMenuItem>
      </SidebarMenu>
    );
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <SidebarMenuButton
          size="lg"
          tooltip={account.name}
          aria-label={`Cuenta: ${account.name}`}
          data-testid="accounts-switcher"
        >
          <AccountTile account={account} getInitials={getInitials} />
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}
