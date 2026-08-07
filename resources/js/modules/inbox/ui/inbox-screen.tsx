import { Head } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';
import { inbox } from '@/routes';
import type { Conversation, InboxPageProps, Message } from '../contracts';
import { ContactSidebar } from './contact-sidebar';
import { ConversationList } from './conversation-list';
import { MessageThread } from './message-thread';

function groupMessages(messages: Message[]): Record<string, Message[]> {
  return messages.reduce<Record<string, Message[]>>((groups, message) => {
    groups[message.conversation_id] ??= [];
    groups[message.conversation_id].push(message);

    return groups;
  }, {});
}

export default function InboxPage({
  conversations: initialConversations,
  messages: initialMessages,
}: InboxPageProps) {
  const [conversations, setConversations] = useState<Conversation[]>(
    () => initialConversations,
  );
  const [activeId, setActiveId] = useState<string | null>(
    () => initialConversations[0]?.id ?? null,
  );
  const [messagesByConv, setMessagesByConv] = useState<
    Record<string, Message[]>
  >(() => groupMessages(initialMessages));

  const activeConversation =
    conversations.find((c) => c.id === activeId) ?? null;
  const messages = activeId ? (messagesByConv[activeId] ?? []) : [];

  function selectConversation(conversation: Conversation) {
    setActiveId(conversation.id);
    setConversations((prev) =>
      prev.map((c) =>
        c.id === conversation.id ? { ...c, unread_count: 0 } : c,
      ),
    );
  }

  function sendMessage(text: string) {
    if (!activeId) return;
    const message: Message = {
      id: `${activeId}-msg-${Date.now()}`,
      conversation_id: activeId,
      sender_type: 'agent',
      content_text: text,
      status: 'sent',
      created_at: new Date().toISOString(),
    };
    setMessagesByConv((prev) => ({
      ...prev,
      [activeId]: [...(prev[activeId] ?? []), message],
    }));
    setConversations((prev) =>
      prev.map((c) =>
        c.id === activeId
          ? {
              ...c,
              last_message_text: text,
              last_message_at: message.created_at,
            }
          : c,
      ),
    );
  }

  return (
    <>
      <Head title="Inbox" />

      <div className="flex h-[calc(100vh-8rem)] overflow-hidden rounded-xl border border-border">
        <ConversationList
          conversations={conversations}
          activeConversationId={activeId}
          onSelect={selectConversation}
        />

        {activeConversation ? (
          <>
            <MessageThread
              conversation={activeConversation}
              messages={messages}
              onSend={sendMessage}
            />
            {activeConversation.contact && (
              <ContactSidebar contact={activeConversation.contact} />
            )}
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
    </>
  );
}

InboxPage.layout = {
  breadcrumbs: [{ title: 'Inbox', href: inbox() }],
};
