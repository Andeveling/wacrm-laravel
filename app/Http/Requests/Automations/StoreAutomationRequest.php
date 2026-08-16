<?php

declare(strict_types=1);

namespace App\Http\Requests\Automations;

use App\Models\Enums\AutomationConnectionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAutomationRequest extends FormRequest
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
            'trigger_type' => ['required', 'string', 'max:100'],
            'connection_mode' => ['required', Rule::enum(AutomationConnectionMode::class)],
            'connection_id' => [
                Rule::requiredIf($this->input('connection_mode') === AutomationConnectionMode::Pinned->value),
                'nullable',
                'uuid',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
