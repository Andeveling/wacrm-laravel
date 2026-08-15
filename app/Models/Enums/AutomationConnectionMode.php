<?php

namespace App\Models\Enums;

enum AutomationConnectionMode: string
{
    case Pinned = 'pinned';
    case Trigger = 'trigger';
}
