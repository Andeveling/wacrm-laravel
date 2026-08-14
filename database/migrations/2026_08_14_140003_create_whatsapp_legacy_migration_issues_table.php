<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_legacy_migration_issues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->uuid('legacy_config_id')->nullable();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('kind');
            $table->jsonb('details');
            $table->char('fingerprint', 64)->unique();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['account_id', 'kind']);
            $table->index('legacy_config_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE whatsapp_legacy_migration_issues ADD CONSTRAINT '
                .'whatsapp_legacy_issue_kind_check CHECK (kind in '
                ."('missing_legacy_connection', 'ambiguous_conversation_connection', "
                ."'waba_claimed_by_another_account', 'phone_number_claimed_by_another_account', "
                ."'incomplete_legacy_config'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_legacy_migration_issues');
    }
};
