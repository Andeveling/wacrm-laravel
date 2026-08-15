<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'content_text' => ['required', 'string', 'max:4096'],
        ];
    }
}
