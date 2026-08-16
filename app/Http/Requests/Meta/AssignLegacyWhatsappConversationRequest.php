<?php

declare(strict_types=1);

namespace App\Http\Requests\Meta;

use Illuminate\Foundation\Http\FormRequest;

final class AssignLegacyWhatsappConversationRequest extends FormRequest
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
            'connection_id' => ['required', 'uuid'],
        ];
    }
}
