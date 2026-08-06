<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $normalizedPhone = preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '';

            if ($normalizedPhone !== '' && Contact::query()->where('phone_normalized', $normalizedPhone)->exists()) {
                $validator->errors()->add('phone', 'Ya existe un contacto con este teléfono.');
            }
        });
    }
}
