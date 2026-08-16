import { Head } from '@inertiajs/react';
import { Bell, CheckCheck, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { notifications } from '@/routes';
import type { Notification } from '../types';

const TYPE_ICON: Record<Notification['type'], typeof Bell> = {
  conversation_assigned: UserPlus,
};

function mockNotifications(): Notification[] {
  return Array.from({ length: 8 }, (_, i) => ({
    id: `notif-${i}`,
    type: 'conversation_assigned',
    conversation_id: `conv-${i}`,
    title: `Andrés te asignó una conversación`,
    body: 'Laura Gómez preguntó por el estado de su pedido.',
    read_at:
      i < 3 ? undefined : new Date(Date.now() - i * 3_600_000).toISOString(),
    created_at: new Date(Date.now() - i * 3_600_000).toISOString(),
  }));
}

function relativeTime(iso: string): string {
  const then = new Date(iso).getTime();
  const diffSec = Math.round((Date.now() - then) / 1000);
  if (diffSec < 60) return `hace ${Math.max(1, diffSec)}s`;
  if (diffSec < 3600) return `hace ${Math.floor(diffSec / 60)}m`;
  if (diffSec < 86400) return `hace ${Math.floor(diffSec / 3600)}h`;
  return `hace ${Math.floor(diffSec / 86400)}d`;
}

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<Notification[]>(() =>
    mockNotifications(),
  );
  const [markingAll, setMarkingAll] = useState(false);

  const unreadIds = notifications.filter((n) => !n.read_at).map((n) => n.id);

  function markRead(id: string) {
    setNotifications((prev) =>
      prev.map((n) =>
        n.id === id && !n.read_at
          ? { ...n, read_at: new Date().toISOString() }
          : n,
      ),
    );
  }

  function markAllRead() {
    if (unreadIds.length === 0) return;
    setMarkingAll(true);
    const now = new Date().toISOString();
    setNotifications((prev) =>
      prev.map((n) => (n.read_at ? n : { ...n, read_at: now })),
    );
    setMarkingAll(false);
  }

  function handleClick(n: Notification) {
    if (!n.read_at) markRead(n.id);
  }

  return (
    <>
      <Head title="Notificaciones" />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              Notificaciones
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Las conversaciones que otros compañeros te asignen aparecen aquí.
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            disabled={unreadIds.length === 0 || markingAll}
            onClick={markAllRead}
          >
            <CheckCheck className="h-4 w-4" />
            Marcar todo como leído
          </Button>
        </div>

        {notifications.length === 0 ? (
          <div className="flex h-48 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/40">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
              <Bell className="h-6 w-6 text-primary" />
            </div>
            <p className="mt-3 text-sm font-medium text-foreground">
              Sin notificaciones todavía
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Verás una alerta aquí cuando alguien te asigne una conversación.
            </p>
          </div>
        ) : (
          <ul className="space-y-2">
            {notifications.map((n) => {
              const Icon = TYPE_ICON[n.type] ?? Bell;
              const isUnread = !n.read_at;
              return (
                <li key={n.id}>
                  <button
                    type="button"
                    onClick={() => handleClick(n)}
                    className={cn(
                      'flex w-full items-start gap-3 rounded-xl border p-4 text-left transition-colors',
                      isUnread
                        ? 'border-primary/30 bg-primary/5 hover:border-primary/50'
                        : 'border-border bg-card hover:border-border/70',
                    )}
                  >
                    <div
                      className={cn(
                        'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg',
                        isUnread ? 'bg-primary/15' : 'bg-muted',
                      )}
                      aria-hidden
                    >
                      <Icon
                        className={cn(
                          'h-5 w-5',
                          isUnread ? 'text-primary' : 'text-muted-foreground',
                        )}
                      />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span
                          className={cn(
                            'truncate text-sm font-semibold',
                            isUnread
                              ? 'text-foreground'
                              : 'text-muted-foreground',
                          )}
                        >
                          {n.title}
                        </span>
                        {isUnread && (
                          <span
                            aria-label="No leído"
                            className="h-2 w-2 flex-shrink-0 rounded-full bg-primary"
                          />
                        )}
                      </div>
                      {n.body && (
                        <p className="mt-0.5 truncate text-xs text-muted-foreground">
                          {n.body}
                        </p>
                      )}
                      <p className="mt-1 text-[11px] text-muted-foreground/70">
                        {relativeTime(n.created_at)}
                      </p>
                    </div>
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </>
  );
}

NotificationsPage.layout = {
  breadcrumbs: [
    {
      title: 'Notificaciones',
      href: notifications(),
    },
  ],
};
