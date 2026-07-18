<?php

namespace App\Console\Commands;

use App\Models\ApiKeyRequest;
use App\Models\Scopes\AccountScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Support query over the `api_key_requests` audit log (story 39): "what
 * happened on Tuesday" for one account. `--account` is required — the
 * command fails closed rather than listing across tenants.
 */
#[Signature('api-keys:audit {--account= : Account id (required)} {--from= : Start of the range, default 7 days ago} {--to= : End of the range, default now}')]
#[Description('List API key audit rows for an account within a date range.')]
class AuditApiKeyRequests extends Command
{
    public function handle(): int
    {
        $accountId = (string) $this->option('account');

        if ($accountId === '') {
            $this->error('The --account option is required.');

            return self::FAILURE;
        }

        $from = $this->option('from') ? Carbon::parse((string) $this->option('from')) : now()->subDays(7);
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to')) : now();

        $rows = ApiKeyRequest::withoutGlobalScope(AccountScope::class)
            ->forAccountBetween($accountId, $from, $to)
            ->orderBy('created_at')
            ->get();

        $this->table(
            ['Timestamp', 'Method', 'Path', 'Status', 'ms', 'Key'],
            $rows->map(fn (ApiKeyRequest $row): array => [
                $row->created_at->toDateTimeString(),
                $row->method,
                $row->path,
                (string) $row->status,
                (string) $row->duration_ms,
                $row->api_key_id,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
