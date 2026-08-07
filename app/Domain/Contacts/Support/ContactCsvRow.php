<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Support;

/**
 * One row parsed by {@see ContactCsvRows}, already normalized: the
 * phone stripped to digits and the tags column split.
 */
final readonly class ContactCsvRow
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $phone,
        public string $normalizedPhone,
        public ?string $name,
        public ?string $email,
        public ?string $company,
        public array $tags,
    ) {}
}
