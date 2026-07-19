<?php

namespace App\Models\Enums;

/**
 * Estado de publicación de un flow. Espeja el CHECK de Supabase 010.
 */
enum FlowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
