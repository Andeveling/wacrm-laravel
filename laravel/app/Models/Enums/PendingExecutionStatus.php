<?php

namespace App\Models\Enums;

/**
 * Estado de una ejecución en cola tras un step `wait` (Supabase 006).
 */
enum PendingExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}
