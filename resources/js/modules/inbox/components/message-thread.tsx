import { Check, CheckCheck, Clock, Send, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Conversation, Message } from '../types';

function StatusIcon({ status }: { status: Message['status'] }) {
  switch (status) {
    case 'sending':
      return <Clock className="size-3 text-muted-foreground" />;
    case 'sent':
      return <Check className="size-3 text-muted-foreground" />;
    case 'delivered':
      return <CheckCheck className="size-3 text-muted-foreground" />;
    case 'read':
      return <CheckCheck className="size-3 text-blue-400" />;
    case 'failed':
      return <XCircle className="size-3 text-red-400" />;
    default:
      return null;
  }
}

interface MessageThreadProps {
  conversation: Conversation;
  messages: Message[];
  onSend: (text: string) => void;
}

export function MessageThread({
  conversation,
  messages,
  onSend,
}: MessageThreadProps) {
  const [input, setInput] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, []);

  function handleSend() {
    const text = input.trim();
    if (!text) return;
    onSend(text);
    setInput('');
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }

  return (
    <div className="flex h-full flex-1 flex-col">
      <header className="flex items-center gap-3 border-b border-border px-4 py-3">
        <div>
          <p className="text-sm font-semibold text-foreground">
            {conversation.contact?.name || conversation.contact?.phone}
          </p>
          <p className="text-xs text-muted-foreground">
            {conversation.contact?.phone}
            {conversation.connection_id ? ' · Conexión fijada' : ''}
          </p>
        </div>
      </header>

      <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto p-4">
        {messages.map((m) => {
          const fromCustomer = m.sender_type === 'customer';
          return (
            <div
              key={m.id}
              className={cn(
                'flex',
                fromCustomer ? 'justify-start' : 'justify-end',
              )}
            >
              <div
                className={cn(
                  'max-w-[75%] rounded-2xl px-3.5 py-2 text-sm',
                  fromCustomer
                    ? 'rounded-bl-sm bg-muted text-foreground'
                    : 'rounded-br-sm bg-primary text-primary-foreground',
                )}
              >
                <p className="whitespace-pre-wrap">{m.content_text}</p>
                <div
                  className={cn(
                    'mt-1 flex items-center justify-end gap-1 text-[10px]',
                    fromCustomer
                      ? 'text-muted-foreground'
                      : 'text-primary-foreground/70',
                  )}
                >
                  {new Date(m.created_at).toLocaleTimeString('es-CO', {
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                  {!fromCustomer && <StatusIcon status={m.status} />}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex items-end gap-2 border-t border-border p-3">
        <textarea
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Escribe un mensaje…"
          rows={1}
          className="flex-1 resize-none rounded-xl border border-border bg-muted px-4 py-2.5 text-sm text-foreground placeholder-muted-foreground outline-none focus:border-primary/50"
        />
        <Button
          size="sm"
          onClick={handleSend}
          disabled={!input.trim()}
          className="h-9 w-9 shrink-0 p-0"
        >
          <Send className="size-4" />
        </Button>
      </div>
    </div>
  );
}
