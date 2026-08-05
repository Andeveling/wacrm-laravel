<?php

namespace App\Models\Enums;

/**
 * Tipo de evento del audit trail de un run. Espeja el CHECK de Supabase 010.
 */
enum FlowRunEventType: string
{
    case Started = 'started';
    case NodeEntered = 'node_entered';
    case MessageSent = 'message_sent';
    case ReplyReceived = 'reply_received';
    case FallbackFired = 'fallback_fired';
    case Handoff = 'handoff';
    case Timeout = 'timeout';
    case Error = 'error';
    case Completed = 'completed';
}
