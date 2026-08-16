<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP CONSTRAINT IF EXISTS whatsapp_webhook_deliveries_processing_state_check');
        DB::statement(<<<'SQL'
            ALTER TABLE whatsapp_webhook_deliveries
                ADD CONSTRAINT whatsapp_webhook_deliveries_processing_state_check
                CHECK (processing_state IN ('received', 'queued', 'processed', 'failed'))
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP CONSTRAINT IF EXISTS whatsapp_webhook_deliveries_processing_state_check');
        DB::statement(<<<'SQL'
            ALTER TABLE whatsapp_webhook_deliveries
                ADD CONSTRAINT whatsapp_webhook_deliveries_processing_state_check
                CHECK (processing_state IN ('received', 'queued', 'processed'))
        SQL);
    }
};
