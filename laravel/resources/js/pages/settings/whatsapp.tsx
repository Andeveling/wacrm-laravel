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

export default function Whatsapp() {
    return (
        <>
            <Head title="WhatsApp" />

            <h1 className="sr-only">WhatsApp</h1>

            <div className="space-y-6">
                <Heading
                    title="WhatsApp"
                    description="Conexión con WhatsApp Business."
                />

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-3">
                            <CardTitle>Conexión con WhatsApp</CardTitle>
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

Whatsapp.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: '/settings',
        },
        {
            title: 'WhatsApp',
        },
    ],
};
