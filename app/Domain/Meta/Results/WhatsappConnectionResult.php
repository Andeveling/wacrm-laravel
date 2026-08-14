<?php

declare(strict_types=1);

namespace App\Domain\Meta\Results;

final readonly class WhatsappConnectionResult
{
    private function __construct(
        public bool $succeeded,
        public string $message,
    ) {}

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
