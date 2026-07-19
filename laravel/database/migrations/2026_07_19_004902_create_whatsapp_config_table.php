<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * whatsapp_config: estado final de Supabase (001 + 013 UNIQUE
     * phone_number_id + 015 registro Meta + 017 account_id UNIQUE).
     * El webhook rutea por phone_number_id y hay un número por cuenta.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE whatsapp_config (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    phone_number_id text NOT NULL,
                    waba_id text,
                    access_token text NOT NULL,
                    verify_token text,
                    status text NOT NULL DEFAULT 'disconnected' CHECK (status IN ('connected', 'disconnected')),
                    connected_at timestamptz,
                    registered_at timestamptz,
                    subscribed_apps_at timestamptz,
                    last_registration_error text,
                    created_at timestamptz DEFAULT now(),
                    updated_at timestamptz DEFAULT now(),
                    CONSTRAINT whatsapp_config_phone_number_id_key UNIQUE (phone_number_id),
                    CONSTRAINT whatsapp_config_account_id_key UNIQUE (account_id)
                )
            SQL);
            DB::statement('CREATE INDEX idx_whatsapp_config_account ON whatsapp_config(account_id)');
            DB::statement(<<<'SQL'
                CREATE INDEX idx_whatsapp_config_registered_at
                    ON whatsapp_config (registered_at)
                    WHERE registered_at IS NULL
            SQL);

            return;
        }

        Schema::create('whatsapp_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('phone_number_id')->unique();
            $table->text('waba_id')->nullable();
            $table->text('access_token');
            $table->text('verify_token')->nullable();
            $table->text('status')->default('disconnected');
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('registered_at')->nullable();
            $table->timestampTz('subscribed_apps_at')->nullable();
            $table->text('last_registration_error')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_config');
    }
};
