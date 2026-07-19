import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { mockAutomations } from '@/lib/automations/mock';

export default function EditAutomationPage({ id }: { id: string }) {
  const automation = mockAutomations().find((a) => a.id === id);

  return (
    <>
      <Head
        title={
          automation ? `Editar ${automation.name}` : 'Editar automatización'
        }
      />

      <div className="mx-auto max-w-2xl space-y-6">
        <Button variant="ghost" size="sm" asChild>
          <Link href="/automations">
            <ArrowLeft className="h-4 w-4" />
            Volver a automatizaciones
          </Link>
        </Button>

        <Card>
          <CardHeader>
            <div className="flex items-start justify-between gap-3">
              <CardTitle>{automation?.name ?? 'Automatización'}</CardTitle>
              <Badge variant="outline">Próximamente</Badge>
            </div>
            <CardDescription>
              El editor visual de disparadores y pasos aún no está disponible.
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    </>
  );
}

EditAutomationPage.layout = {
  breadcrumbs: [
    { title: 'Automatizaciones', href: '/automations' },
    { title: 'Editar' },
  ],
};
