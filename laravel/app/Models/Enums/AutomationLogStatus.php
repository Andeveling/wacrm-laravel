<?php

namespace App\Models\Enums;

/**
 * Resultado de una ejecución de automation. Espeja el CHECK de Supabase 006.
 */
enum AutomationLogStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
}
