<?php

namespace App\Models\Enums;

/**
 * Escalera forward-only de un destinatario de difusión (Supabase 001/005):
 * cada estado implica los anteriores; 'failed' solo desde pending/sent.
 */
enum BroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Replied = 'replied';
    case Failed = 'failed';
}
