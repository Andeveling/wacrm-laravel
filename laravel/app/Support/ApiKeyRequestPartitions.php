<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates the monthly `api_key_requests_YYYY_MM` range partition a given
 * month needs before rows for it can be inserted. Postgres-only: the table
 * is only declared `PARTITION BY RANGE` on the `pgsql` driver (see the
 * `create_api_key_requests_table` migration) — sqlite, used in tests, gets a
 * plain unpartitioned table instead, so this is a no-op there.
 *
 * A `DEFAULT` partition (created by the migration) catches anything outside
 * the explicitly created range, so a missing monthly partition never causes
 * a failed insert — it just loses the per-month pruning benefit until the
 * next partition is created.
 */
final class ApiKeyRequestPartitions
{
    public static function ensure(CarbonInterface $month): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $start = $month->clone()->startOfMonth();
        $end = $start->clone()->addMonthNoOverflow();
        $name = 'api_key_requests_'.$start->format('Y_m');

        DB::statement(sprintf(
            "CREATE TABLE IF NOT EXISTS %s PARTITION OF api_key_requests FOR VALUES FROM ('%s') TO ('%s')",
            $name,
            $start->toDateString(),
            $end->toDateString()
        ));
    }
}
