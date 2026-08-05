<?php

namespace App\Models\Enums;

/**
 * Estado de un deal del pipeline. Espeja el CHECK de Supabase 002.
 */
enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
}
