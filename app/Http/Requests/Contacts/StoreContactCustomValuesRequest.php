<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreContactCustomValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'values' => ['present', 'array'],
            'values.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string|null> */
    public function values(): array
    {
        return $this->validated('values');
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customFieldIds = array_keys((array) $this->input('values', []));

            if ($customFieldIds === []) {
                return;
            }

            $validCount = CustomField::query()->whereIn('id', $customFieldIds)->count();

            if ($validCount !== count($customFieldIds)) {
                $validator->errors()->add('values', 'Uno o más campos personalizados no existen en esta cuenta.');
            }
        });
    }
}
