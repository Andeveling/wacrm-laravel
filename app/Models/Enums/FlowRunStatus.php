<?php

namespace App\Models\Enums;

/**
 * Estado de un run de flow. Espeja el CHECK de Supabase 010.
 */
enum FlowRunStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case HandedOff = 'handed_off';
    case TimedOut = 'timed_out';
    case PausedByAgent = 'paused_by_agent';
    case Failed = 'failed';
}
