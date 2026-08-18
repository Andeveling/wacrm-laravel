import { Head, useHttp } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { inbox } from '@/routes';
import { seen } from '@/routes/inbox/conversations';
import { useInboxLive } from '../hooks/use-inbox-live';
import { useInboxSend } from '../hooks/use-inbox-send';
import { applyInboxMessage, type InboxMessagePayload } from '../model';
import type { Conversation, InboxPageProps, Message } from '../types';
import { ContactSidebar } from './contact-sidebar';
import { ConversationList } from './conversation-list';
import { MessageThread } from './message-thread';
import { NewConversationDialog } from './new-conversation-dialog';

function messagesForConversation(
  messages: Message[],
  conversationId: string | null,
): Message[] {
  if (conversationId === null) {
    return [];
  }

  return messages.filter(
    (message) => message.conversation_id === conversationId,
  );
}

export default function InboxPage({
  conversations: initialConversations,
  messages: initialMessages,
  contacts,
  connections,
  can_mark_seen: canMarkSeen,
}: InboxPageProps) {
  const { submit } = useHttp();
  const [conversations, setConversations] = useState<Conversation[]>(
    () => initialConversations,
  );
  const [activeId, setActiveId] = useState<string | null>(
    () => initialConversations[0]?.id ?? null,
  );
  const [newConversationOpen, setNewConversationOpen] = useState(false);
  const { messages, sending, send, retry, receive } = useInboxSend(
    activeId,
    initialMessages,
  );
  const conversationsRef = useRef(conversations);
  const knownMessageIds = useRef(
    new Set(initialMessages.map((message) => message.id)),
  );
  conversationsRef.current = conversations;

  useEffect(() => {
    for (const message of messages) {
      knownMessageIds.current.add(message.id);
    }
  }, [messages]);

  const threadMessages = messagesForConversation(messages, activeId);

  const activeConversation =
    conversations.find((c) => c.id === activeId) ?? null;

  const persistSeen = useCallback(
    (conversationId: string) => {
      if (!canMarkSeen) {
        return;
      }

      setConversations((prev) =>
        prev.map((conversation) =>
          conversation.id === conversationId
            ? { ...conversation, unread_count: 0 }
            : conversation,
        ),
      );

      void submit(seen(conversationId)).catch(() => undefined);
    },
    [canMarkSeen, submit],
  );

  useEffect(() => {
    if (!activeId) {
      return;
    }

    persistSeen(activeId);
  }, [activeId, persistSeen]);

  const onLiveMessage = useCallback(
    (payload: InboxMessagePayload) => {
      if (knownMessageIds.current.has(payload.message.id)) {
        return;
      }

      knownMessageIds.current.add(payload.message.id);
      setConversations(
        applyInboxMessage(
          { conversations: conversationsRef.current, messages: [] },
          payload,
        ).conversations,
      );
      receive(payload.message);

      if (
        payload.message.sender_type === 'customer' &&
        payload.conversation.id === activeId
      ) {
        persistSeen(payload.conversation.id);
      }
    },
    [activeId, persistSeen, receive],
  );

  useInboxLive(onLiveMessage);

  function selectConversation(conversation: Conversation) {
    setActiveId(conversation.id);
  }

  return (
    <>
      <Head title="Inbox" />

      <div className="flex h-[calc(100vh-8rem)] overflow-hidden rounded-xl border border-border">
        <ConversationList
          conversations={conversations}
          activeConversationId={activeId}
          onSelect={selectConversation}
          onNewConversation={() => setNewConversationOpen(true)}
        />

        {activeConversation ? (
          <>
            <MessageThread
              conversation={activeConversation}
              messages={threadMessages}
              sending={sending}
              onSend={send}
              onRetry={retry}
            />
            {activeConversation.contact ? (
              <ContactSidebar contact={activeConversation.contact} />
            ) : null}
          </>
        ) : (
          <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
            <MessageSquare className="size-10" />
            <p className="text-sm">
              {conversations.length === 0
                ? 'No hay conversaciones en esta cuenta.'
                : 'Selecciona una conversación para empezar.'}
            </p>
          </div>
        )}
      </div>
      <NewConversationDialog
        open={newConversationOpen}
        onOpenChange={setNewConversationOpen}
        contacts={contacts}
        connections={connections}
      />
    </>
  );
}

InboxPage.layout = {
  breadcrumbs: [{ title: 'Inbox', href: inbox() }],
};
