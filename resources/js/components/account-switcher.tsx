import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar';
import { useInitials } from '@/hooks/use-initials';
import { useIsMobile } from '@/hooks/use-mobile';
import { accountTypeLabel } from '@/lib/account-type-label';
import { update as switchAccount } from '@/routes/accounts/switch';
import type { AccountMembership, AccountType } from '@/types/auth';

function AccountTile({
  name,
  type,
  getInitials,
}: {
  name: string;
  type: AccountType;
  getInitials: (name: string) => string;
}) {
  return (
    <>
      <Avatar className="size-8 rounded-lg">
        <AvatarFallback className="rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
          {getInitials(name)}
        </AvatarFallback>
      </Avatar>
      <div className="grid flex-1 text-left text-sm leading-tight">
        <span className="truncate font-medium">{name}</span>
        <span className="truncate text-xs">{accountTypeLabel(type)}</span>
      </div>
    </>
  );
}

export function AccountSwitcher() {
  const { currentAccount: account, accounts } = usePage().props;
  const getInitials = useInitials();
  const { state } = useSidebar();
  const isMobile = useIsMobile();

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

  const currentId = account.id;
  const trigger = (
    <>
      <AccountTile
        name={account.name}
        type={account.type}
        getInitials={getInitials}
      />
      {accounts.length > 1 ? <ChevronsUpDown className="ml-auto" /> : null}
    </>
  );

  if (accounts.length < 2) {
    return (
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            size="lg"
            tooltip={account.name}
            aria-label={`Cuenta: ${account.name}`}
            data-testid="accounts-switcher"
          >
            {trigger}
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    );
  }

  function switchTo(membership: AccountMembership): void {
    if (membership.id === currentId) {
      return;
    }

    router.post(switchAccount.url(membership.id));
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <SidebarMenuButton
                size="lg"
                tooltip={account.name}
                aria-label={`Cuenta: ${account.name}`}
                data-testid="accounts-switcher"
              />
            }
          >
            {trigger}
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-(--anchor-width) min-w-56 rounded-lg"
            align="start"
            side={
              isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'
            }
          >
            {accounts.map((membership) => {
              const current = membership.id === account.id;

              return (
                <DropdownMenuItem
                  key={membership.id}
                  data-testid={`account-switcher-item-${membership.id}`}
                  onClick={() => switchTo(membership)}
                >
                  <AccountTile
                    name={membership.name}
                    type={membership.type}
                    getInitials={getInitials}
                  />
                  {current ? <Check className="ml-auto" /> : null}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}
