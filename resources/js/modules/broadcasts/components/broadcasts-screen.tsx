import { Head, Link } from '@inertiajs/react';
import { Plus, Radio } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { broadcasts } from '@/routes';
import { newMethod, show } from '@/routes/broadcasts';
import { getBroadcastStatus } from '../constants/status';
import type { Broadcast } from '../types';

function percent(numerator: number, denominator: number): number {
  if (!denominator) return 0;
  return Math.round((numerator / denominator) * 100);
}

function RateCell({
  value,
  total,
  color,
}: {
  value: number;
  total: number;
  color: string;
}) {
  const pct = percent(value, total);
  return (
    <div className="flex items-center gap-2">
      <span className="w-10 text-right text-xs tabular-nums text-muted-foreground">
        {pct}%
      </span>
      <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
        <div
          className={`h-1.5 rounded-full ${color}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

export default function BroadcastsPage({
  broadcasts: broadcastList,
}: {
  broadcasts: Broadcast[];
}) {
  return (
    <>
      <Head title="Difusiones" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Difusiones</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Envía mensajes de plantilla a varios contactos a la vez.
            </p>
          </div>
          <Button asChild>
            <Link href={newMethod()}>
              <Plus className="h-4 w-4" />
              Nueva difusión
            </Link>
          </Button>
        </div>

        {broadcasts.length === 0 ? (
          <div className="flex h-64 flex-col items-center justify-center rounded-xl border border-border bg-card">
            <Radio className="mb-3 h-10 w-10 text-muted-foreground" />
            <p className="text-sm font-medium text-foreground">
              Sin difusiones todavía
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Crea tu primera difusión para empezar.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead className="hidden md:table-cell">
                    Plantilla
                  </TableHead>
                  <TableHead className="hidden text-right sm:table-cell">
                    Destinatarios
                  </TableHead>
                  <TableHead className="hidden lg:table-cell">
                    Entrega
                  </TableHead>
                  <TableHead className="hidden lg:table-cell">
                    Lectura
                  </TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead className="hidden sm:table-cell">Fecha</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {broadcastList.map((broadcast) => {
                  const status = getBroadcastStatus(broadcast.status);
                  return (
                    <TableRow key={broadcast.id} className="cursor-pointer">
                      <TableCell className="font-medium text-foreground">
                        <Link href={show(broadcast.id)} className="block">
                          {broadcast.name}
                        </Link>
                      </TableCell>
                      <TableCell className="hidden text-muted-foreground md:table-cell">
                        {broadcast.template_name}
                      </TableCell>
                      <TableCell className="hidden text-right text-muted-foreground tabular-nums sm:table-cell">
                        {broadcast.total_recipients}
                      </TableCell>
                      <TableCell className="hidden lg:table-cell">
                        <RateCell
                          value={broadcast.delivered_count}
                          total={broadcast.total_recipients}
                          color="bg-primary"
                        />
                      </TableCell>
                      <TableCell className="hidden lg:table-cell">
                        <RateCell
                          value={broadcast.read_count}
                          total={broadcast.total_recipients}
                          color="bg-blue-500"
                        />
                      </TableCell>
                      <TableCell>
                        <span
                          className={`inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium ${status.classes}`}
                        >
                          {status.pulse ? (
                            <span className="relative flex h-1.5 w-1.5">
                              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-yellow-400 opacity-75" />
                              <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-yellow-400" />
                            </span>
                          ) : null}
                          {status.label}
                        </span>
                      </TableCell>
                      <TableCell className="hidden text-muted-foreground sm:table-cell">
                        {new Date(broadcast.created_at).toLocaleDateString(
                          'es-CO',
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
        )}
      </div>
    </>
  );
}

BroadcastsPage.layout = {
  breadcrumbs: [
    {
      title: 'Difusiones',
      href: broadcasts(),
    },
  ],
};
