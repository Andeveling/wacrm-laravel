<?php

declare(strict_types=1);

namespace App\Http\Requests\Broadcasts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_id' => ['required', 'uuid'],
            'audience_type' => ['required', Rule::in(['all', 'tags'])],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', 'distinct'],
            'template_variables' => ['present', 'array'],
            'template_variables.*' => ['string', 'max:1000'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
