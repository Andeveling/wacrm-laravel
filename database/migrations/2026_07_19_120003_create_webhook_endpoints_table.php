<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * webhook_endpoints: estado final de Supabase (028). `secret` guarda
     * el signing secret HMAC cifrado por la app (cast encrypted en el
     * modelo). `record_webhook_failure` (028) pasa a PHP (inventario #2).
     * `events` es text[] en pgsql; sqlite lo aproxima como jsonb. El cast
     * del modelo queda para el módulo runtime: el cast `array` de Eloquent
     * escribe JSON, incompatible con el literal `{a,b}` de text[].
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE webhook_endpoints (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    created_by bigint REFERENCES users(id) ON DELETE SET NULL,
                    url text NOT NULL,
                    secret text NOT NULL,
                    events text[] NOT NULL DEFAULT '{}',
                    is_active boolean NOT NULL DEFAULT true,
                    last_delivery_at timestamptz,
                    failure_count integer NOT NULL DEFAULT 0,
                    created_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX webhook_endpoints_account_id_idx ON webhook_endpoints (account_id)');

            return;
        }

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('url');
            $table->text('secret');
            $table->jsonb('events')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_delivery_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
