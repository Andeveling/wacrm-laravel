<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\BindTenantAccount;

/**
 * Marks a queued Job as tenant-scoped. The job's constructor must set
 * `$this->accountId` (a public property, so it travels serialized with the
 * job to the queue); this trait then binds it via job middleware before
 * `handle()` runs, so any `BelongsToAccount` model the job touches sees the
 * right tenant instead of failing closed.
 */
trait TenantAware
{
    public string $accountId;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new BindTenantAccount($this->accountId)];
    }
}
