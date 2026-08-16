<?php

declare(strict_types=1);

namespace App\Domain\Meta\Support;

use App\Support\CurrentAccount;

final readonly class WhatsappConnectionAttempt
{
    public function __construct(
        public CurrentAccount $account,
        public string $phoneNumberId,
        public string $wabaId,
        public string $token,
    ) {}
}
