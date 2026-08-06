<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

final class BulkDestroyContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ];
    }
}
