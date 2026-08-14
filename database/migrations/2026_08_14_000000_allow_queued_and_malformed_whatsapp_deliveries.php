<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries ALTER COLUMN raw_payload DROP NOT NULL');
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP CONSTRAINT IF EXISTS whatsapp_webhook_deliveries_processing_state_check');
            DB::statement(<<<'SQL'
                ALTER TABLE whatsapp_webhook_deliveries
                    ADD CONSTRAINT whatsapp_webhook_deliveries_processing_state_check
                    CHECK (processing_state IN ('received', 'queued'))
            SQL);

            return;
        }

        Schema::table('whatsapp_webhook_deliveries', function (Blueprint $table): void {
            $table->jsonb('raw_payload')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Signed malformed deliveries can have a NULL payload. Replacing
        // them during rollback loses forensic evidence, so use a forward
        // migration rather than pretending this change is reversible.
        throw new LogicException('Cannot safely roll back malformed WhatsApp webhook delivery support.');
    }
};
