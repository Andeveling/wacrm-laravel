<?php

namespace App\Jobs\Middleware;

use App\Models\Scopes\AccountScope;
use Closure;

/**
 * Job middleware that binds the tenant account before the job's `handle()`
 * runs. Queued jobs never have `AccountScope::CONTAINER_KEY` bound — there is
 * no `EnsureCurrentAccount`/`AuthenticateApiKey` middleware on a worker
 * process — so without this, any `BelongsToAccount` model touched inside a
 * job fails closed (zero rows) silently.
 */
class BindTenantAccount
{
    public function __construct(private readonly string $accountId) {}

    public function handle(mixed $job, Closure $next): mixed
    {
        app()->instance(AccountScope::CONTAINER_KEY, $this->accountId);

        return $next($job);
    }
}
