<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input boundary for the legacy POST /invitations endpoint. Shape only —
 * the admin+ check lives in the Action, which reads the tenant from
 * CurrentAccount.
 *
 * Owner is intentionally absent from the accepted roles: this route
 * predates ADR 0002 and never issued Owner invitations. The
 * account-scoped POST /accounts/{account}/members endpoint is the
 * supported path for that.
 */
final class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the Action (CurrentAccount::isAdmin).
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:admin,member,viewer'],
            'label' => ['nullable', 'string', 'max:80'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,365'],
        ];
    }
}
