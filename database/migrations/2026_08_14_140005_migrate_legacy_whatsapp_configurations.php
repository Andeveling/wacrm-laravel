<?php

use App\Domain\Meta\Services\LegacyWhatsappConfigurationMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyWhatsappConfigurationMigrator::class)->run();
    }

    public function down(): void
    {
        // The source rows and CRM history are intentionally retained. A
        // forward remediation is safer than pretending a data backfill can
        // be reversed without losing operator decisions.
    }
};
