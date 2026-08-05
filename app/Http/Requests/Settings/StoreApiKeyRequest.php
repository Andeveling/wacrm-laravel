<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\Enums\ApiScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input boundary for POST /settings/api-keys. Authorization (admin+) is
 * gated in the controller via CurrentAccount::isAdmin() — this request
 * only validates shape.
 */
final class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'scopes' => ['array'],
            'scopes.*' => [Rule::enum(ApiScope::class)],
        ];
    }
}
