<?php

namespace Tests\Fixtures;

use App\Jobs\Concerns\TenantAware;
use App\Models\Scopes\AccountScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Minimal queued Job used only to exercise the TenantAware trait in tests.
 * Records what account was bound and how many tenant-scoped rows were
 * visible from inside `handle()`, so the test can assert both without
 * needing a real domain Job.
 */
class TenantAwareJobFixture implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public static ?string $boundAccountId = null;

    public static ?int $visibleRowCount = null;

    public function __construct(string $accountId, public string $name)
    {
        $this->accountId = $accountId;
    }

    public function handle(): void
    {
        TenantScopedFixture::create(['name' => $this->name]);

        static::$boundAccountId = app(AccountScope::CONTAINER_KEY);
        static::$visibleRowCount = TenantScopedFixture::count();
    }
}
