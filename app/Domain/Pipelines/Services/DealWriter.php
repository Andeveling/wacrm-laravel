<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;

final class DealWriter
{
    /** @param array<string, mixed> $attributes */
    public function store(Pipeline $pipeline, array $attributes, int $userId): void
    {
        $pipeline->stages()->whereKey($attributes['stage_id'])->firstOrFail();
        $contactId = $attributes['contact_id'] ?? null;

        if ($contactId !== null) {
            Contact::query()->findOrFail($contactId);
        }

        Deal::create([
            ...$attributes,
            'value' => $attributes['value'] ?? 0,
            'contact_id' => $contactId,
            'pipeline_id' => $pipeline->id,
            'user_id' => $userId,
            'assigned_to' => $userId,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Deal $deal, array $attributes): void
    {
        if (array_key_exists('stage_id', $attributes)) {
            $deal->pipeline->stages()->whereKey($attributes['stage_id'])->firstOrFail();
        }

        if (array_key_exists('contact_id', $attributes) && $attributes['contact_id'] !== null) {
            Contact::query()->findOrFail($attributes['contact_id']);
        }

        $deal->update($attributes);
    }
}
