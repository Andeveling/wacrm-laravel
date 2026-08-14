<?php

declare(strict_types=1);

namespace App\Domain\Meta\Support;

use RuntimeException;

final class MetaGraphException extends RuntimeException
{
    public function __construct(
        public readonly string $operation,
        string $message,
        public readonly ?int $metaCode = null,
    ) {
        parent::__construct($message);
    }
}
