<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'field_name' => ['required', 'string', 'max:100', Rule::unique('custom_fields', 'field_name')->where('account_id', session('current_account_id'))],
            'field_type' => ['required', 'string', 'in:text,number,date,select'],
            'field_options' => ['nullable', 'array'],
        ];
    }
}
