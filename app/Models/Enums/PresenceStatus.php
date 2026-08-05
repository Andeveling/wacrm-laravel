<?php

namespace App\Models\Enums;

/**
 * Presencia persistida de un miembro (Supabase 024). "Offline" no se
 * almacena: se deriva de la antigüedad de last_seen_at.
 */
enum PresenceStatus: string
{
    case Online = 'online';
    case Away = 'away';
}
