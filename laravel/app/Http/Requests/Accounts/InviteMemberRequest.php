<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Rules\NotMemberOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input boundary for the POST /accounts/{account}/members endpoint
 * (issue #44). Authorization happens inside the Action via
 * AccountPolicy::manageMembers — this request only validates shape.
 *
 * Notes:
 *  - role accepts any AccountRole value, including Owner: ADR 0002
 *    protects against demoting/detaching the last Owner at the
 *    Application layer (see ChangeMemberRole / RemoveMember), and
 *    inviting a future Owner is one of the two legal paths to add
 *    one (the other being a direct ChangeMemberRole promotion).
 *  - The NotMemberOfAccount rule runs the cross-row check before
 *    the Action ever sees the request, so duplicate-invite attempts
 *    surface as 422 validation errors instead of partial writes.
 */
final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the Action (AccountPolicy::manageMembers).
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        abort_unless($this->route('account') instanceof Account, 404);
        $account = $this->route('account');

        return [
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                new NotMemberOfAccount($account),
            ],
            'role' => [
                'required',
                'string',
                Rule::enum(AccountRole::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'The :attribute must be a valid email address.',
            'role.Illuminate\\Validation\\Rules\\Enum' => 'The :attribute must be one of: owner, admin, member, viewer.',
        ];
    }
}
