import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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

export default function Overview({ panels }: PageProps) {
    return (
        <>
            <Head title="Settings" />

            <h1 className="sr-only">Settings</h1>

            <div className="space-y-6">
                <Heading
                    title="Settings"
                    description="Paneles de configuración disponibles en tu cuenta."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    {panels.map((panel) => {
                        const body = (
                            <Card>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <CardTitle>{panel.title}</CardTitle>
                                        <Badge
                                            className={
                                                statusBadgeClass[panel.status]
                                            }
                                        >
                                            {panel.status}
                                        </Badge>
                                    </div>
                                    <CardDescription>
                                        {panel.description}
                                    </CardDescription>
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
