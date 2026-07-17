<?php

namespace App\Models\Enums;

enum AccountType: string
{
    case Personal = 'personal';
    case Team = 'team';
}
