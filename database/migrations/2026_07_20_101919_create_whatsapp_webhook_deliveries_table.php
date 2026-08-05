<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * whatsapp_webhook_deliveries: bandeja de entrada durable del webhook
     * de Meta WhatsApp Business (#64). Cada entrega firmada se persiste
     * ANTES de devolver 200, de modo que un fallo del worker posterior
     * nunca se traduce en una reentrega de Meta. `processing_state`
     * refleja el ciclo de vida del inbox:
     *
     *   - `received`        : persiste y 200 OK, aún sin despachar.
     *   - `persistence_failed` : la fila no se grabó; respondimos 503.
     *
     * Los tickets #66+ amplían el catálogo de estados (e.g. `dispatched`,
     * `processed`, `failed_permanent`). El payload crudo se conserva 30
     * días para auditoría y reproceso (limpieza vía comando Artisan
     * diario). Los IDs de Meta (`phone_number_id`, `wamid`) viven en el
     * payload JSON; no se promueve ninguna columna hasta que #66 los
     * necesite de verdad.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE whatsapp_webhook_deliveries (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    signature_header text,
                    raw_payload jsonb NOT NULL,
                    content_length integer NOT NULL,
                    received_at timestamptz NOT NULL DEFAULT now(),
                    processed_at timestamptz,
                    processing_state text NOT NULL DEFAULT 'received'
                        CHECK (processing_state IN ('received', 'persistence_failed')),
                    last_error text,
                    created_at timestamptz DEFAULT now()
                )
            SQL);
            DB::statement(<<<'SQL'
                CREATE INDEX idx_whatsapp_webhook_deliveries_received_at
                    ON whatsapp_webhook_deliveries (received_at)
            SQL);

            return;
        }

        Schema::create('whatsapp_webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('signature_header')->nullable();
            $table->jsonb('raw_payload');
            $table->integer('content_length');
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->text('processing_state')->default('received');
            $table->text('last_error')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_deliveries');
    }
};
