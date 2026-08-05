<?php

namespace App\Models\Enums;

/**
 * Disparador de un flow. Espeja el CHECK de Supabase 010.
 */
enum FlowTriggerType: string
{
    case Keyword = 'keyword';
    case FirstInboundMessage = 'first_inbound_message';
    case Manual = 'manual';
}
