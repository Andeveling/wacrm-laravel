<?php

namespace App\Console\Commands;

use App\Models\ApiKeyRequest;
use App\Models\Scopes\AccountScope;
use App\Support\ApiKeyRequestPartitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Deletes `api_key_requests` rows older than the retention window and
 * provisions next month's partition, so the table never runs out of a
 * partition to insert into. Meant to run daily (see `routes/console.php`).
 */
#[Signature('api-keys:prune-audit {--older-than=90days : Retention window, e.g. 90days}')]
#[Description('Prune API key audit log rows older than the retention window.')]
class PruneApiKeyAuditLog extends Command
{
    public function handle(): int
    {
        $days = $this->parseDays((string) $this->option('older-than'));
        $cutoff = now()->subDays($days);

        ApiKeyRequestPartitions::ensure(now()->addMonthNoOverflow());

        // ponytail: plain DELETE scan across the whole (partitioned) table;
        // upgrade to DROP TABLE per fully-stale monthly partition once audit
        // volume makes this slow.
        $deleted = ApiKeyRequest::withoutGlobalScope(AccountScope::class)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} api_key_requests row(s) older than {$days} day(s).");

        return self::SUCCESS;
    }

    private function parseDays(string $window): int
    {
        if (! preg_match('/^(\d+)days$/', $window, $matches)) {
            throw new InvalidArgumentException("Invalid --older-than value [{$window}]. Expected format: <N>days.");
        }

        return (int) $matches[1];
    }
}
