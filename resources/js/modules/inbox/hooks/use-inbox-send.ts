import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { store as storeInboxMessage } from '@/routes/inbox/messages';
import type {
  Message,
  MessageStatus,
  SenderType,
  ThreadMessage,
} from '../types';

const SEND_TIMEOUT_MS = 20_000;

function persistedMessage(value: unknown): ThreadMessage | null {
  if (typeof value !== 'object' || value === null) {
    return null;
  }

  const payload = value as Partial<ThreadMessage>;

  if (
    typeof payload.id !== 'string' ||
    typeof payload.conversation_id !== 'string' ||
    !isSenderType(payload.sender_type) ||
    typeof payload.content_text !== 'string' ||
    !isMessageStatus(payload.status) ||
    typeof payload.created_at !== 'string'
  ) {
    return null;
  }

  return {
    id: payload.id,
    conversation_id: payload.conversation_id,
    sender_type: payload.sender_type,
    content_text: payload.content_text,
    status: payload.status,
    created_at: payload.created_at,
  };
}

function isSenderType(value: unknown): value is SenderType {
  return value === 'customer' || value === 'agent' || value === 'bot';
}

function isMessageStatus(value: unknown): value is MessageStatus {
  return (
    value === 'sending' ||
    value === 'sent' ||
    value === 'delivered' ||
    value === 'read' ||
    value === 'failed'
  );
}

export function useInboxSend(
  conversationId: string | null,
  initialMessages: Message[],
) {
  const { setData, post, processing, cancel } = useHttp({
    content_text: '',
  });
  const [messages, setMessages] = useState<ThreadMessage[]>(
    () => initialMessages,
  );
  const timeoutRef = useRef<number | null>(null);
  const timedOutRef = useRef(false);
  const inFlightRef = useRef(false);

  const clearSendTimeout = useCallback(() => {
    if (timeoutRef.current !== null) {
      window.clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
  }, []);

  useEffect(() => {
    return () => {
      clearSendTimeout();
    };
  }, [clearSendTimeout]);

  const markFailed = useCallback((tempId: string, timedOut: boolean) => {
    setMessages((current) =>
      current.map((message) =>
        message.id === tempId
          ? { ...message, status: 'failed', timeout_failed: timedOut }
          : message,
      ),
    );
  }, []);

  const send = useCallback(
    (text: string) => {
      if (!conversationId || processing || inFlightRef.current) {
        return;
      }

      inFlightRef.current = true;

      const tempId = `temp-${crypto.randomUUID()}`;
      const optimistic: ThreadMessage = {
        id: tempId,
        conversation_id: conversationId,
        sender_type: 'agent',
        content_text: text,
        status: 'sending',
        created_at: new Date().toISOString(),
      };

      setMessages((current) => [...current, optimistic]);
      setData({ content_text: text });
      timedOutRef.current = false;
      timeoutRef.current = window.setTimeout(() => {
        timedOutRef.current = true;
        cancel();
      }, SEND_TIMEOUT_MS);

      void post(storeInboxMessage.url(conversationId), {
        onSuccess: (response) => {
          const persisted = persistedMessage(response);

          if (persisted === null) {
            markFailed(tempId, false);

            return;
          }

          setMessages((current) =>
            current.map((message) =>
              message.id === tempId ? persisted : message,
            ),
          );
        },
        onError: () => {
          markFailed(tempId, false);
        },
        onHttpException: () => {
          markFailed(tempId, false);
        },
        onNetworkError: () => {
          markFailed(tempId, false);
        },
        onCancel: () => {
          markFailed(tempId, timedOutRef.current);
        },
        onFinish: () => {
          inFlightRef.current = false;
          clearSendTimeout();
        },
      });
    },
    [
      cancel,
      clearSendTimeout,
      conversationId,
      markFailed,
      post,
      processing,
      setData,
    ],
  );

  const retry = useCallback(
    (message: ThreadMessage) => {
      if (processing || inFlightRef.current) {
        return;
      }

      setMessages((current) => current.filter((row) => row.id !== message.id));
      send(message.content_text);
    },
    [processing, send],
  );

  return {
    messages,
    sending: processing,
    send,
    retry,
  };
}
