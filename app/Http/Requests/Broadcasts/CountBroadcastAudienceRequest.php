<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcasts;

use Illuminate\Foundation\Http\FormRequest;

final class CountBroadcastAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', 'distinct'],
        ];
    }
}
