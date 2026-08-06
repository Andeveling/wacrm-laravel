import type { BroadcastStatus, RecipientStatus } from './contracts';

export interface StatusDisplay {
  label: string;
  classes: string;
  /** True for statuses that should pulse to convey "live / in-flight". */
  pulse?: boolean;
}

export const broadcastStatusConfig: Record<BroadcastStatus, StatusDisplay> = {
  draft: {
    label: 'Borrador',
    classes: 'bg-slate-500/10 text-muted-foreground border-slate-500/20',
  },
  scheduled: {
    label: 'Programado',
    classes: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  },
  sending: {
    label: 'Enviando',
    classes: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
    pulse: true,
  },
  sent: {
    label: 'Enviado',
    classes: 'bg-primary/10 text-primary border-primary/20',
  },
  failed: {
    label: 'Fallido',
    classes: 'bg-red-500/10 text-red-400 border-red-500/20',
  },
};

export const recipientStatusConfig: Record<RecipientStatus, StatusDisplay> = {
  pending: {
    label: 'Pendiente',
    classes: 'bg-slate-500/10 text-muted-foreground border-slate-500/20',
  },
  sent: {
    label: 'Enviado',
    classes: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  },
  delivered: {
    label: 'Entregado',
    classes: 'bg-primary/10 text-primary border-primary/20',
  },
  read: {
    label: 'Leído',
    classes: 'bg-primary/10 text-primary border-primary/20',
  },
  replied: {
    label: 'Respondido',
    classes: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
  },
  failed: {
    label: 'Fallido',
    classes: 'bg-red-500/10 text-red-400 border-red-500/20',
  },
};

/** Tolerant lookup — falls back to draft/pending so the UI never crashes on an unknown value. */
export function getBroadcastStatus(status: string): StatusDisplay {
  return (
    broadcastStatusConfig[status as BroadcastStatus] ??
    broadcastStatusConfig.draft
  );
}

export function getRecipientStatus(status: string): StatusDisplay {
  return (
    recipientStatusConfig[status as RecipientStatus] ??
    recipientStatusConfig.pending
  );
}
