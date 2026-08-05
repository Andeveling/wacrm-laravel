<?php

namespace App\Models\Enums;

/**
 * Estado de conexión del número de WhatsApp de la cuenta. Espeja el
 * CHECK de Supabase 001. "Credenciales guardadas pero no registradas"
 * se distingue por registered_at, no por este status (ver 015).
 */
enum WhatsappConfigStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
}
