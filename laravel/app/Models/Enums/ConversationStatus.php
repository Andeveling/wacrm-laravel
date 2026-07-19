<?php

namespace App\Models\Enums;

/**
 * Estado de una conversación del inbox. Espeja el CHECK de Supabase 001.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';
}
