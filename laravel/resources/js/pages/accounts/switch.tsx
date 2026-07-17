import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/accounts/switch';
import type { Account } from '@/types';

export default function SwitchAccount({ accounts }: { accounts: Account[] }) {
    return (
        <>
            <Head title="Switch account" />

            <div className="space-y-6">
                <Heading
                    title="Switch account"
                    description="Choose which account you want to work in."
                />

                <ul className="divide-y divide-sidebar-border rounded-lg border border-sidebar-border">
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
            </div>
        </>
    );
}
