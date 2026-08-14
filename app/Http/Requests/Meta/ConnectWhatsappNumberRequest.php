<?php

declare(strict_types=1);

namespace App\Http\Requests\Meta;

use Illuminate\Foundation\Http\FormRequest;

final class ConnectWhatsappNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone_number_id' => ['required', 'string', 'max:128'],
            'waba_id' => ['required', 'string', 'max:128'],
            'access_token' => ['nullable', 'string', 'max:8192'],
            'pin' => ['nullable', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number_id' => is_string($this->input('phone_number_id'))
                ? trim($this->input('phone_number_id'))
                : $this->input('phone_number_id'),
            'waba_id' => is_string($this->input('waba_id'))
                ? trim($this->input('waba_id'))
                : $this->input('waba_id'),
            'access_token' => is_string($this->input('access_token'))
                ? trim($this->input('access_token'))
                : $this->input('access_token'),
            'pin' => is_string($this->input('pin'))
                ? trim($this->input('pin'))
                : $this->input('pin'),
        ]);
    }
}
