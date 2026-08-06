import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Conversation, ConversationStatus } from '../contracts';

type InboxFilter = ConversationStatus | 'all' | 'unread';

const FILTER_OPTIONS: { label: string; value: InboxFilter }[] = [
  { label: 'Todas', value: 'all' },
  { label: 'No leídas', value: 'unread' },
  { label: 'Abiertas', value: 'open' },
  { label: 'Pendientes', value: 'pending' },
  { label: 'Cerradas', value: 'closed' },
];

const STATUS_COLORS: Record<ConversationStatus, string> = {
  open: 'bg-primary',
  pending: 'bg-amber-500',
  closed: 'bg-muted-foreground',
};

function initials(name?: string, fallback?: string) {
  const source = (name || fallback || '?').trim();
  return source.charAt(0).toUpperCase();
}

function relativeTime(iso?: string): string {
  if (!iso) return '';
  const diffSec = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
  if (diffSec < 60) return 'ahora';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m`;
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h`;
  return `${Math.floor(diffSec / 86400)}d`;
}

interface ConversationListProps {
  conversations: Conversation[];
  activeConversationId: string | null;
  onSelect: (conversation: Conversation) => void;
}

export function ConversationList({
  conversations,
  activeConversationId,
  onSelect,
}: ConversationListProps) {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<InboxFilter>('all');

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return conversations.filter((c) => {
      const matchesTerm =
        !term ||
        c.contact?.name?.toLowerCase().includes(term) ||
        c.contact?.phone.includes(term);
      const matchesFilter =
        filter === 'all' ||
        (filter === 'unread' ? c.unread_count > 0 : c.status === filter);
      return matchesTerm && matchesFilter;
    });
  }, [conversations, search, filter]);

  return (
    <div className="flex h-full w-full flex-col border-r border-border sm:w-80">
      <div className="space-y-2 border-b border-border p-3">
        <div className="relative">
          <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar conversación…"
            className="pl-8"
          />
        </div>
        <div className="flex flex-wrap gap-1">
          {FILTER_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setFilter(opt.value)}
              className={cn(
                'rounded-full px-2.5 py-1 text-xs font-medium transition-colors',
                filter === opt.value
                  ? 'bg-secondary text-secondary-foreground'
                  : 'text-muted-foreground hover:bg-muted',
              )}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        {filtered.length === 0 ? (
          <p className="p-4 text-center text-sm text-muted-foreground">
            Sin conversaciones.
          </p>
        ) : (
          <ul className="divide-y divide-border">
            {filtered.map((c) => (
              <li key={c.id}>
                <button
                  type="button"
                  onClick={() => onSelect(c)}
                  className={cn(
                    'flex w-full items-center gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/50',
                    activeConversationId === c.id && 'bg-muted',
                  )}
                >
                  <div className="relative shrink-0">
                    <Avatar className="size-9">
                      <AvatarFallback className="text-xs">
                        {initials(c.contact?.name, c.contact?.phone)}
                      </AvatarFallback>
                    </Avatar>
                    <span
                      className={cn(
                        'absolute right-0 bottom-0 size-2.5 rounded-full ring-2 ring-background',
                        STATUS_COLORS[c.status],
                      )}
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-sm font-medium text-foreground">
                        {c.contact?.name || c.contact?.phone}
                      </span>
                      <span className="shrink-0 text-[11px] text-muted-foreground">
                        {relativeTime(c.last_message_at)}
                      </span>
                    </div>
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-xs text-muted-foreground">
                        {c.last_message_text}
                      </span>
                      {c.unread_count > 0 && (
                        <span className="flex size-4.5 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground">
                          {c.unread_count}
                        </span>
                      )}
                    </div>
                  </div>
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
