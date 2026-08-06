import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/accounts/switch';
import type { Account } from '../contracts';

export default function SwitchAccount({ accounts }: { accounts: Account[] }) {
  return (
    <>
      <Head title="Switch account" />

      <div className="space-y-6">
        <Heading
          title="Switch account"
          description="Choose which account you want to work in."
        />

        <div
          className="rounded-lg border border-sidebar-border"
          data-testid="accounts-switcher"
        >
          {accounts.length === 0 && (
            <div
              className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center"
              data-testid="accounts-switcher-empty"
            >
              <p className="font-medium">No pertenecés a ninguna cuenta</p>
              <p className="text-sm text-muted-foreground">
                Si esto es un error, contactá al administrador para que te
                invite a una organización.
              </p>
            </div>
          )}

          {accounts.length > 0 && (
            <ul className="divide-y divide-sidebar-border">
              {accounts.map((account) => (
                <li
                  key={account.id}
                  className="flex items-center justify-between gap-4 p-4"
                >
                  <div>
                    <p className="font-medium">{account.name}</p>
                    <p className="text-sm text-muted-foreground capitalize">
                      {account.type}
                    </p>
                  </div>

                  <Button asChild>
                    <Link href={update(account.id)} method="post" as="button">
                      Switch
                    </Link>
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </>
  );
}
