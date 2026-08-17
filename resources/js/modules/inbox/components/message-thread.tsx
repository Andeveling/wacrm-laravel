import { Check, CheckCheck, Send, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
  type Conversation,
  INBOX_TIMEOUT_COPY,
  type ThreadMessage,
} from '../types';

function StatusIcon({ status }: { status: ThreadMessage['status'] }) {
  switch (status) {
    case 'sending':
      return <Spinner className="size-3" />;
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

function bubbleTestId(status: ThreadMessage['status']): string | undefined {
  if (status === 'sending') {
    return 'inbox-message-sending';
  }

  if (status === 'failed') {
    return 'inbox-message-failed';
  }

  return undefined;
}

function MessageBubble({
  message,
  sending,
  onRetry,
}: {
  message: ThreadMessage;
  sending: boolean;
  onRetry: (message: ThreadMessage) => void;
}) {
  const fromCustomer = message.sender_type === 'customer';

  return (
    <div
      data-testid={bubbleTestId(message.status)}
      className={cn('flex', fromCustomer ? 'justify-start' : 'justify-end')}
    >
      <div
        className={cn(
          'max-w-[75%] rounded-2xl px-3.5 py-2 text-sm',
          fromCustomer
            ? 'rounded-bl-sm bg-muted text-foreground'
            : 'rounded-br-sm bg-primary text-primary-foreground',
        )}
      >
        <p className="whitespace-pre-wrap">{message.content_text}</p>
        <div
          className={cn(
            'mt-1 flex items-center justify-end gap-1 text-[10px]',
            fromCustomer
              ? 'text-muted-foreground'
              : 'text-primary-foreground/70',
          )}
        >
          {new Date(message.created_at).toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit',
          })}
          {!fromCustomer && <StatusIcon status={message.status} />}
        </div>
        {message.status === 'failed' ? (
          <div className="mt-2 space-y-1">
            {message.timeout_failed ? (
              <p className="text-[10px] text-primary-foreground/80">
                {INBOX_TIMEOUT_COPY}
              </p>
            ) : null}
            <Button
              type="button"
              size="sm"
              variant="secondary"
              data-testid="inbox-retry-send"
              disabled={sending}
              onClick={() => onRetry(message)}
              className="h-7 px-2 text-xs"
            >
              Reintentar
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  );
}

interface MessageThreadProps {
  conversation: Conversation;
  messages: ThreadMessage[];
  sending: boolean;
  onSend: (text: string) => void;
  onRetry: (message: ThreadMessage) => void;
}

export function MessageThread({
  conversation,
  messages,
  sending,
  onSend,
  onRetry,
}: MessageThreadProps) {
  const [input, setInput] = useState('');
  const scrollRef = useRef<HTMLDivElement>(null);

  // biome-ignore lint/correctness/useExhaustiveDependencies: scroll when the thread gains or replaces a bubble
  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, [messages]);

  function handleSend() {
    const text = input.trim();
    if (!text || sending) return;
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
        {messages.map((message) => (
          <MessageBubble
            key={message.id}
            message={message}
            sending={sending}
            onRetry={onRetry}
          />
        ))}
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
          type="button"
          data-testid="inbox-send-button"
          onClick={handleSend}
          disabled={sending || !input.trim()}
          className="h-9 w-9 shrink-0 p-0"
        >
          {sending ? <Spinner /> : <Send className="size-4" />}
        </Button>
      </div>
    </div>
  );
}
