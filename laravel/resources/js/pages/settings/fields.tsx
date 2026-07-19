import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Fields() {
    return (
        <>
            <Head title="Campos personalizados" />

            <h1 className="sr-only">Campos personalizados</h1>

            <div className="space-y-6">
                <Heading
                    title="Campos personalizados"
                    description="Campos extra para contactos y negocios."
                />

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <CardTitle>Campos personalizados</CardTitle>
                            <Badge className="border-transparent bg-zinc-200 text-zinc-700">
                                Próximamente
                            </Badge>
                        </div>
                        <CardDescription>
                            Esta sección aún no está disponible.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild variant="outline">
                            <Link href="/settings">Volver a Settings</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Fields.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings',
        },
        {
            title: 'Campos personalizados',
        },
    ],
};
