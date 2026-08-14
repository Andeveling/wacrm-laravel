<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

interface MetaGraphClientContract
{
    /**
     * @return array{id: string, display_phone_number: string|null, verified_name: string|null}
     */
    public function verifyPhoneAndWaba(string $phoneNumberId, string $wabaId, string $token): array;

    public function subscribeWaba(string $wabaId, string $token): void;

    public function registerPhoneNumber(string $phoneNumberId, string $token, string $pin): void;
}
