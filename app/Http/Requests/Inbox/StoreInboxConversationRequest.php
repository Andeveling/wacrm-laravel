<?php

declare(strict_types=1);

namespace App\Http\Requests\Inbox;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInboxConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'uuid'],
            'connection_id' => ['nullable', 'uuid'],
        ];
    }
}
