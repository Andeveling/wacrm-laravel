<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounts;

use App\Models\Enums\AccountRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input boundary for changing an Account member's role. The role must
 * be one of the AccountRole cases (Owner / Admin / Member / Viewer);
 * any other string fails validation BEFORE the Action ever runs, so
 * the domain layer only ever sees legal values. Authorization and
 * Owner Protection live in the Action — this class only validates
 * shape.
 */
class ChangeMemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(AccountRole::class)],
        ];
    }

    /**
     * Typed accessor used by the Action — the Request's role value
     * has already been validated against the enum, so this conversion
     * cannot fail at runtime.
     */
    public function newRole(): AccountRole
    {
        return AccountRole::from((string) $this->input('role'));
    }
}
