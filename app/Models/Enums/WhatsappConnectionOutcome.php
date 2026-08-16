<?php

declare(strict_types=1);

namespace App\Models\Enums;

enum WhatsappConnectionOutcome: string
{
    case Success = 'success';
    case Incomplete = 'incomplete';
    case Failure = 'failure';
}
