import { Head } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';
import { inbox } from '@/routes';
import { useInboxSend } from '../hooks/use-inbox-send';
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
}: InboxPageProps) {
  const [conversations, setConversations] = useState<Conversation[]>(
    () => initialConversations,
  );
  const [activeId, setActiveId] = useState<string | null>(
    () => initialConversations[0]?.id ?? null,
  );
  const [newConversationOpen, setNewConversationOpen] = useState(false);
  const { messages, sending, send, retry } = useInboxSend(
    activeId,
    initialMessages,
  );
  const threadMessages = messagesForConversation(messages, activeId);

  const activeConversation =
    conversations.find((c) => c.id === activeId) ?? null;

  function selectConversation(conversation: Conversation) {
    setActiveId(conversation.id);
    setConversations((prev) =>
      prev.map((c) =>
        c.id === conversation.id ? { ...c, unread_count: 0 } : c,
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
