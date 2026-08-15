<?php

namespace App\Models\Enums;

/**
 * Estado de una difusión. Espeja el CHECK de Supabase 001.
 */
enum BroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Paused = 'paused';
}
