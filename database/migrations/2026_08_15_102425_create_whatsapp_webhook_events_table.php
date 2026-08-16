<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_id')->constrained('whatsapp_webhook_deliveries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('connection_id')->nullable()->constrained('whatsapp_phone_number_connections')->nullOnDelete();
            $table->text('phone_number_id')->nullable();
            $table->text('fingerprint');
            $table->text('classification');
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['delivery_id', 'fingerprint']);
            $table->index('classification');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE whatsapp_webhook_events ADD CONSTRAINT whatsapp_webhook_events_classification_check '
                ."CHECK (classification IN ('processed', 'unresolved', 'unsupported', 'blocked', 'uncorrelated', 'failed'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
