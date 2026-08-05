<?php

namespace App\Models\Enums;

/**
 * Tipo de nodo de un flow. Espeja el CHECK de Supabase 010 + 016 (send_media).
 */
enum FlowNodeType: string
{
    case Start = 'start';
    case SendButtons = 'send_buttons';
    case SendList = 'send_list';
    case SendMessage = 'send_message';
    case SendMedia = 'send_media';
    case CollectInput = 'collect_input';
    case Condition = 'condition';
    case SetTag = 'set_tag';
    case Handoff = 'handoff';
    case HttpFetch = 'http_fetch';
    case End = 'end';
}
