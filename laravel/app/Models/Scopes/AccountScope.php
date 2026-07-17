<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class AccountScope implements Scope
{
    /**
     * Container binding key holding the current tenant's account id.
     * The `EnsureCurrentAccount` middleware binds this per-request.
     */
    public const CONTAINER_KEY = 'currentAccountId';

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound(self::CONTAINER_KEY)) {
            $builder->where($model->qualifyColumn('account_id'), app(self::CONTAINER_KEY));

            return;
        }

        // ponytail: fail closed (no rows) when no tenant context is bound, instead of
        // leaking every account's rows. Bind self::CONTAINER_KEY explicitly to see data.
        $builder->whereRaw('1 = 0');
    }
}
