import type { MessageTemplateStatus } from './contracts';

export interface TemplateStatusDisplay {
  label: string;
  classes: string;
}

export const templateStatusConfig: Record<
  MessageTemplateStatus,
  TemplateStatusDisplay
> = {
  DRAFT: {
    label: 'Borrador',
    classes: 'bg-slate-600/20 text-muted-foreground border-slate-600/30',
  },
  PENDING: {
    label: 'Pendiente',
    classes: 'bg-yellow-600/20 text-yellow-400 border-yellow-600/30',
  },
  APPROVED: {
    label: 'Aprobada',
    classes: 'bg-primary/20 text-primary border-primary/30',
  },
  REJECTED: {
    label: 'Rechazada',
    classes: 'bg-red-600/20 text-red-400 border-red-600/30',
  },
};
