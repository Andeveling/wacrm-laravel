<?php

namespace App\Concerns;

use App\Models\Scopes\AccountScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes every query on the model to the current tenant account and
 * auto-populates account_id on create so no insert lands tenant-less.
 *
 * For admin tools and batch jobs that must see every account's rows,
 * bypass the scope explicitly: `Model::withoutGlobalScope(AccountScope::class)`.
 */
trait BelongsToAccount
{
    /**
     * Boot the trait: scope every query to the current account and
     * auto-populate account_id on create so no insert lands tenant-less.
     */
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(new AccountScope);

        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('account_id')) && app()->bound(AccountScope::CONTAINER_KEY)) {
                $model->setAttribute('account_id', app(AccountScope::CONTAINER_KEY));
            }
        });
    }
}
