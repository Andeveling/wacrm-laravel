<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

final class ImportContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
