import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Send, Trash2, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { getBroadcastStatus, getRecipientStatus } from '@/lib/broadcast-status';
import { mockBroadcasts, mockRecipients } from '@/lib/broadcasts/mock';
import type { RecipientStatus } from '@/types';

const RECIPIENT_STATUSES: readonly RecipientStatus[] = [
  'pending',
  'sent',
  'delivered',
  'read',
  'replied',
  'failed',
];

function StatCard({
  label,
  value,
  total,
  icon,
  color,
}: {
  label: string;
  value: number;
  total: number;
  icon: React.ReactNode;
  color: string;
}) {
  const pct = total > 0 ? Math.round((value / total) * 100) : 0;
  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <div className="flex items-center justify-between">
        <div
          className={`flex h-8 w-8 items-center justify-center rounded-lg ${color}`}
        >
          {icon}
        </div>
        <span className="text-xs text-muted-foreground">{pct}%</span>
      </div>
      <p className="mt-3 text-2xl font-bold text-foreground">
        {value.toLocaleString()}
      </p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}

function FunnelChart({
  steps,
}: {
  steps: { label: string; value: number; color: string }[];
}) {
  const max = Math.max(...steps.map((s) => s.value), 1);
  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <h3 className="mb-4 text-sm font-medium text-foreground">Embudo</h3>
      <div className="space-y-2">
        {steps.map((step) => {
          const pctOfMax = Math.max(5, Math.round((step.value / max) * 100));
          const pctOfSent =
            steps[0].value > 0
              ? Math.round((step.value / steps[0].value) * 100)
              : 0;
          return (
            <div key={step.label} className="flex items-center gap-3">
              <span className="w-20 shrink-0 text-xs text-muted-foreground">
                {step.label}
              </span>
              <div className="relative h-7 flex-1 rounded-full bg-muted">
                <div
                  className={`h-7 rounded-full ${step.color} transition-[width] duration-500`}
                  style={{ width: `${pctOfMax}%` }}
                />
                <span className="absolute inset-0 flex items-center px-3 text-xs font-medium text-foreground">
                  {step.value.toLocaleString()}
                  <span className="ml-2 text-muted-foreground/80">
                    ({pctOfSent}%)
                  </span>
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function toCsv(rows: string[][]): string {
  const quote = (v: string) => `"${v.replace(/"/g, '""')}"`;
  return rows.map((r) => r.map(quote).join(',')).join('\n');
}

function downloadBlob(filename: string, content: string) {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

export default function BroadcastDetailPage({ id }: { id: string }) {
  const [broadcast] = useState(
    () => mockBroadcasts().find((b) => b.id === id) ?? mockBroadcasts()[0],
  );
  const [recipients] = useState(() => mockRecipients(broadcast.id));
  const [statusFilter, setStatusFilter] = useState<RecipientStatus | 'all'>(
    'all',
  );
  const [confirmDelete, setConfirmDelete] = useState(false);

  const filteredRecipients = useMemo(
    () =>
      statusFilter === 'all'
        ? recipients
        : recipients.filter((r) => r.status === statusFilter),
    [recipients, statusFilter],
  );

  function handleExport() {
    const header = [
      'Contacto',
      'Teléfono',
      'Estado',
      'Enviado',
      'Entregado',
      'Leído',
      'Error',
    ];
    const rows = recipients.map((r) => [
      r.contact?.name ?? '',
      r.contact?.phone ?? '',
      r.status,
      r.sent_at ?? '',
      r.delivered_at ?? '',
      r.read_at ?? '',
      r.error_message ?? '',
    ]);
    downloadBlob(`difusion-${broadcast.id}.csv`, toCsv([header, ...rows]));
  }

  function handleDelete() {
    toast.success('Difusión eliminada.');
  }

  const status = getBroadcastStatus(broadcast.status);
  const funnelSteps = [
    { label: 'Enviado', value: broadcast.sent_count, color: 'bg-primary' },
    {
      label: 'Entregado',
      value: broadcast.delivered_count,
      color: 'bg-teal-500',
    },
    { label: 'Leído', value: broadcast.read_count, color: 'bg-blue-500' },
    {
      label: 'Respondido',
      value: broadcast.replied_count,
      color: 'bg-indigo-500',
    },
  ];

  return (
    <>
      <Head title={broadcast.name} />

      <div className="space-y-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex items-center gap-4">
            <Button variant="outline" size="icon" asChild>
              <Link href="/broadcasts">
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-2xl font-bold text-foreground">
                  {broadcast.name}
                </h1>
                <span
                  className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${status.classes}`}
                >
                  {status.label}
                </span>
              </div>
              <div className="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
                <span>Plantilla: {broadcast.template_name}</span>
                <span>·</span>
                <span>
                  Creada el{' '}
                  {new Date(broadcast.created_at).toLocaleDateString('es-CO')}
                </span>
              </div>
            </div>
          </div>

          {confirmDelete ? (
            <div className="flex items-center gap-2 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-1.5 text-sm">
              <span className="text-red-300">¿Eliminar esta difusión?</span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setConfirmDelete(false)}
                className="h-7"
              >
                Cancelar
              </Button>
              <Button
                size="sm"
                onClick={handleDelete}
                className="h-7 bg-red-600 text-white hover:bg-red-700"
              >
                Confirmar
              </Button>
            </div>
          ) : (
            <Button
              variant="outline"
              size="sm"
              disabled={broadcast.status === 'sending'}
              onClick={() => setConfirmDelete(true)}
              className="border-red-500/30 text-red-400 hover:bg-red-500/10"
            >
              <Trash2 className="h-3.5 w-3.5" />
              Eliminar
            </Button>
          )}
        </div>

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatCard
            label="Destinatarios"
            value={broadcast.total_recipients}
            total={broadcast.total_recipients}
            icon={<Users className="h-4 w-4" />}
            color="bg-muted text-muted-foreground"
          />
          <StatCard
            label="Enviado"
            value={broadcast.sent_count}
            total={broadcast.total_recipients}
            icon={<Send className="h-4 w-4" />}
            color="bg-primary/10 text-primary"
          />
          <StatCard
            label="Entregado"
            value={broadcast.delivered_count}
            total={broadcast.total_recipients}
            icon={<Send className="h-4 w-4" />}
            color="bg-teal-500/10 text-teal-400"
          />
          <StatCard
            label="Leído"
            value={broadcast.read_count}
            total={broadcast.total_recipients}
            icon={<Send className="h-4 w-4" />}
            color="bg-blue-500/10 text-blue-400"
          />
          <StatCard
            label="Respondido"
            value={broadcast.replied_count}
            total={broadcast.total_recipients}
            icon={<Send className="h-4 w-4" />}
            color="bg-indigo-500/10 text-indigo-400"
          />
          <StatCard
            label="Fallido"
            value={broadcast.failed_count}
            total={broadcast.total_recipients}
            icon={<Send className="h-4 w-4" />}
            color="bg-red-500/10 text-red-400"
          />
        </div>

        <FunnelChart steps={funnelSteps} />

        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium text-foreground">
              Destinatarios
            </h3>
            <div className="flex items-center gap-2">
              <Select
                value={statusFilter}
                onValueChange={(v) =>
                  setStatusFilter(v as RecipientStatus | 'all')
                }
              >
                <SelectTrigger className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los estados</SelectItem>
                  {RECIPIENT_STATUSES.map((s) => (
                    <SelectItem key={s} value={s}>
                      {getRecipientStatus(s).label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button variant="outline" size="sm" onClick={handleExport}>
                <Download className="h-3.5 w-3.5" />
                Exportar CSV
              </Button>
            </div>
          </div>

          <div className="overflow-x-auto rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Contacto</TableHead>
                  <TableHead>Teléfono</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead className="hidden sm:table-cell">
                    Enviado
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredRecipients.map((r) => {
                  const recStatus = getRecipientStatus(r.status);
                  return (
                    <TableRow key={r.id}>
                      <TableCell className="font-medium text-foreground">
                        {r.contact?.name || 'Desconocido'}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">
                        {r.contact?.phone}
                      </TableCell>
                      <TableCell>
                        <span
                          className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${recStatus.classes}`}
                        >
                          {recStatus.label}
                        </span>
                      </TableCell>
                      <TableCell className="hidden text-xs text-muted-foreground sm:table-cell">
                        {r.sent_at
                          ? new Date(r.sent_at).toLocaleString('es-CO')
                          : '—'}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </div>
        </div>
      </div>
    </>
  );
}

BroadcastDetailPage.layout = {
  breadcrumbs: [
    { title: 'Difusiones', href: '/broadcasts' },
    { title: 'Detalle' },
  ],
};
