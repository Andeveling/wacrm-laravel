<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use App\Models\Enums\DealStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'uuid'],
            'contact_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expected_close_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'nullable', new Enum(DealStatus::class)],
        ];
    }
}
