<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('access_token')->nullable();
            $table->uuid('legacy_config_id')->nullable()->unique();
            $table->timestampsTz();

            $table->unique(['id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_integrations');
    }
};
