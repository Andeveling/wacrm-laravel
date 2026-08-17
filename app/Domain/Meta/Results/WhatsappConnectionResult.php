<?php

declare(strict_types=1);

namespace App\Domain\Meta\Results;

use App\Models\Enums\WhatsappConnectionOutcome;

final readonly class WhatsappConnectionResult
{
    private function __construct(
        public WhatsappConnectionOutcome $outcome,
        public string $message,
    ) {}

    public static function success(string $message): self
    {
        return new self(WhatsappConnectionOutcome::Success, $message);
    }

    public static function incomplete(string $message): self
    {
        return new self(WhatsappConnectionOutcome::Incomplete, $message);
    }

    public static function failure(string $message): self
    {
        return new self(WhatsappConnectionOutcome::Failure, $message);
    }

    public function succeeded(): bool
    {
        return $this->outcome === WhatsappConnectionOutcome::Success;
    }

    public function keepsDraft(): bool
    {
        return ! $this->succeeded();
    }

    public function flashKey(): string
    {
        return match ($this->outcome) {
            WhatsappConnectionOutcome::Success => 'whatsapp_status',
            WhatsappConnectionOutcome::Incomplete => 'whatsapp_notice',
            WhatsappConnectionOutcome::Failure => 'whatsapp_error',
        };
    }
}
