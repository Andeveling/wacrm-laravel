<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_phone_number_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('waba_subscription_id')->nullable();
            $table->text('phone_number_id')->nullable()->unique();
            $table->text('readiness')->default('credentials_verified');
            $table->boolean('is_default')->default(false);
            $table->uuid('legacy_config_id')->nullable()->unique();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('registered_at')->nullable();
            $table->text('last_registration_error')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'account_id']);
            $table->foreign(['waba_subscription_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('waba_subscriptions')
                ->cascadeOnDelete();
            $table->index(['account_id', 'waba_subscription_id']);
            $table->index(['account_id', 'readiness']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE whatsapp_phone_number_connections ADD CONSTRAINT '
                .'whatsapp_connections_readiness_check CHECK (readiness in '
                ."('credentials_verified', 'subscribed', 'webhook_waiting', 'active', "
                ."'attention_required', 'disconnected'))",
            );
            DB::statement(
                'ALTER TABLE whatsapp_phone_number_connections ADD CONSTRAINT '
                .'whatsapp_connections_default_check CHECK '
                ."(is_default = false or readiness = 'active')",
            );
        }

        $where = Schema::getConnection()->getDriverName() === 'pgsql'
            ? 'is_default = true'
            : 'is_default = 1';

        DB::statement(
            'CREATE UNIQUE INDEX whatsapp_connections_one_default_per_account '
            ."ON whatsapp_phone_number_connections (account_id) WHERE {$where}",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_number_connections');
    }
};
