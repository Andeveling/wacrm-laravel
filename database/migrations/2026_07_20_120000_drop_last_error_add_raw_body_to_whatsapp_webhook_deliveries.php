<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `raw_body` (text) to `whatsapp_webhook_deliveries` so we persist
     * the byte-exact HTTP request Meta signed, not the JSON-decoded
     * array. Code-review #64 finding (5) — the spec calls for "persiste
     * payload crudo" but the first commit only kept the decoded array,
     * which loses key order, duplicate keys and number formatting on
     * round-trip. `raw_payload jsonb` is kept as a typed companion for
     * downstream queries (#66+), but `raw_body` is now the source of
     * truth.
     *
     * `last_error` and the `persistence_failed` enum value were dropped:
     * they were defined in #64 but never written anywhere. The
     * controller returns `503` directly when the persistence call throws,
     * without recording a row, so a `persistence_failed` state had no
     * producer. The CHECK is now collapsed to a single allowed value.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP COLUMN IF EXISTS last_error');
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries ADD COLUMN raw_body text NOT NULL DEFAULT \'\'');
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries ALTER COLUMN raw_body DROP DEFAULT');
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP CONSTRAINT IF EXISTS whatsapp_webhook_deliveries_processing_state_check');
            DB::statement(<<<'SQL'
                ALTER TABLE whatsapp_webhook_deliveries
                    ADD CONSTRAINT whatsapp_webhook_deliveries_processing_state_check
                    CHECK (processing_state IN ('received'))
            SQL);

            return;
        }

        Schema::table('whatsapp_webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn('last_error');
            $table->text('raw_body');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP CONSTRAINT IF EXISTS whatsapp_webhook_deliveries_processing_state_check');
            DB::statement(<<<'SQL'
                ALTER TABLE whatsapp_webhook_deliveries
                    ADD CONSTRAINT whatsapp_webhook_deliveries_processing_state_check
                    CHECK (processing_state IN ('received','persistence_failed'))
            SQL);
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries DROP COLUMN IF EXISTS raw_body');
            DB::statement('ALTER TABLE whatsapp_webhook_deliveries ADD COLUMN last_error text');

            return;
        }

        Schema::table('whatsapp_webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn('raw_body');
            $table->text('last_error')->nullable();
        });
    }
};
