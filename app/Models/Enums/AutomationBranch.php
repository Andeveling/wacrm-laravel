<?php

namespace App\Models\Enums;

/**
 * Rama de un step condicional de automation. Espeja el CHECK de Supabase 006.
 */
enum AutomationBranch: string
{
    case Yes = 'yes';
    case No = 'no';
}
