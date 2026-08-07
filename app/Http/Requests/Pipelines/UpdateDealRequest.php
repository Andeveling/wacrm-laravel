<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use App\Models\Enums\DealStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'stage_id' => ['sometimes', 'required', 'uuid'],
            'contact_id' => ['sometimes', 'nullable', 'uuid'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', new Enum(DealStatus::class)],
        ];
    }
}
