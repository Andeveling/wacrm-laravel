import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Layers3 } from 'lucide-react';
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
import type { Flow } from '../types';

export default function FlowEditorPage({ flow }: { flow: Flow }) {
  const nodes = flow.nodes ?? [];

  return (
    <>
      <Head title={flow.name} />
      <div className="mx-auto max-w-4xl space-y-6 p-6">
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
                {flow.name}
              </CardTitle>
              <Badge variant="outline">{nodes.length} nodos</Badge>
            </div>
            <CardDescription>
              {flow.description ??
                'Editor visual del flow real cargado desde la base de datos.'}
            </CardDescription>
          </CardHeader>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Layers3 className="h-4 w-4 text-primary" />
              Nodos
            </CardTitle>
            <CardDescription>
              Ordenados por posición real del flow.
            </CardDescription>
          </CardHeader>
          <div className="grid gap-3 border-t border-border p-4">
            {nodes.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Este flow todavía no tiene nodos.
              </p>
            ) : (
              nodes.map((node) => (
                <div
                  key={node.id}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-background px-4 py-3 text-sm"
                >
                  <div className="min-w-0">
                    <p className="font-medium text-foreground">
                      {node.node_key}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {node.node_type} · {node.position_x}, {node.position_y}
                    </p>
                  </div>
                  <Badge variant="outline">
                    {Object.keys(node.config).length} campos
                  </Badge>
                </div>
              ))
            )}
          </div>
        </Card>
        <Button variant="outline" asChild>
          <Link href={runs(flow.id)}>Ver historial de ejecuciones</Link>
        </Button>
      </div>
    </>
  );
}

FlowEditorPage.layout = {
  breadcrumbs: [{ title: 'Flujos', href: flows() }, { title: 'Editor' }],
};
