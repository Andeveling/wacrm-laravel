<?php

declare(strict_types=1);

namespace App\Domain\Inbox\Support;

use App\Models\Conversation;

final readonly class InboxMessageSend
{
    public function __construct(
        public Conversation $conversation,
        public string $phoneNumberId,
        public string $token,
        public string $to,
        public string $connectionId,
    ) {}
}
