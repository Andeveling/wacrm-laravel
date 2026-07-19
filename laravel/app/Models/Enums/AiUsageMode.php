<?php

namespace App\Models\Enums;

/**
 * Superficie que gastó los tokens. Espeja el CHECK de Supabase 033.
 */
enum AiUsageMode: string
{
    case AutoReply = 'auto_reply';
    case Draft = 'draft';
}
