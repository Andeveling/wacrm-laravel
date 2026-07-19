<?php

namespace App\Models\Enums;

/**
 * Enum crudo de Meta para el estado de una plantilla (Supabase 014):
 * conservar los valores exactos importa — PAUSED es recuperable, DISABLED
 * queda fuera 30 días, IN_APPEAL no debe editarse.
 */
enum MessageTemplateStatus: string
{
    case Draft = 'DRAFT';
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Paused = 'PAUSED';
    case Disabled = 'DISABLED';
    case InAppeal = 'IN_APPEAL';
    case PendingDeletion = 'PENDING_DELETION';
}
