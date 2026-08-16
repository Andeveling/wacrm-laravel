<?php

declare(strict_types=1);

namespace App\Models\Enums;

enum WhatsappConnectionOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
}
