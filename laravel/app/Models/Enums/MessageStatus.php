<?php

namespace App\Models\Enums;

/**
 * Estado de entrega de un mensaje de WhatsApp (escalera de Meta:
 * sending → sent → delivered → read, o failed). Espeja el CHECK de 001.
 */
enum MessageStatus: string
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
}
