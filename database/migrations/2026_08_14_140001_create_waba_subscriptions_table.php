<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waba_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_id')->nullable();
            $table->text('waba_id')->nullable()->unique();
            $table->uuid('legacy_config_id')->nullable()->unique();
            $table->timestampTz('subscribed_apps_at')->nullable();
            $table->timestampsTz();

            $table->unique(['account_id', 'id']);
            $table->foreign(['integration_id', 'account_id'])
                ->references(['id', 'account_id'])
                ->on('whatsapp_integrations')
                ->cascadeOnDelete();
            $table->index(['account_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waba_subscriptions');
    }
};
