import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { flows } from '@/routes';
import { runs } from '@/routes/flows';
import { mockFlows } from '../fixtures';

export default function FlowEditorPage({ id }: { id: string }) {
  const flow = mockFlows().find((f) => f.id === id);

  return (
    <>
      <Head title={flow ? flow.name : 'Editor de flujo'} />

      <div className="mx-auto max-w-2xl space-y-6 p-6">
        <Button variant="ghost" size="sm" asChild>
          <Link href={flows()}>
            <ArrowLeft className="h-4 w-4" />
            Volver a flujos
          </Link>
        </Button>

        <Card>
          <CardHeader>
            <div className="flex items-start justify-between gap-3">
              <CardTitle className="flex items-center gap-2">
                <FileText className="h-4 w-4 text-primary" />
                {flow?.name ?? 'Flujo'}
              </CardTitle>
              <Badge variant="outline">Próximamente</Badge>
            </div>
            <CardDescription>
              El editor visual de flujos (lienzo de nodos) aún no está
              disponible.
            </CardDescription>
          </CardHeader>
        </Card>

        <Button variant="outline" asChild>
          <Link href={runs(id)}>Ver historial de ejecuciones</Link>
        </Button>
      </div>
    </>
  );
}

FlowEditorPage.layout = {
  breadcrumbs: [{ title: 'Flujos', href: flows() }, { title: 'Editor' }],
};
