<?php

namespace App\Models;

use App\Models\Enums\AccountRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $account_id
 * @property int $user_id
 * @property AccountRole $role
 * @property Carbon|null $joined_at
 */
class AccountUser extends Pivot
{
    protected $table = 'account_user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AccountRole::class,
            'joined_at' => 'datetime',
        ];
    }
}
