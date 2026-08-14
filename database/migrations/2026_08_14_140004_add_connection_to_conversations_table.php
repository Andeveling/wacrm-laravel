<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignUuid('connection_id')->nullable()->after('contact_id');
            $table->unique(['account_id', 'contact_id', 'connection_id'], 'idx_conversations_account_contact_connection');
            $table->foreign(['account_id', 'connection_id'])
                ->references(['account_id', 'id'])
                ->on('whatsapp_phone_number_connections')
                ->restrictOnDelete();
        });

        DB::statement('DROP INDEX idx_conversations_account_contact');

        DB::statement(
            'CREATE UNIQUE INDEX idx_conversations_account_contact_without_connection '
            .'ON conversations (account_id, contact_id) WHERE connection_id IS NULL',
        );
    }

    public function down(): void
    {
        // This rollback is intentionally conservative: if multiple connection-
        // specific conversations now exist, restoring the old uniqueness would
        // correctly fail instead of deleting or merging CRM history.
        DB::statement('DROP INDEX idx_conversations_account_contact_without_connection');

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropForeign(['account_id', 'connection_id']);
            $table->dropUnique('idx_conversations_account_contact_connection');
            $table->dropColumn('connection_id');
        });

        DB::statement('CREATE UNIQUE INDEX idx_conversations_account_contact ON conversations (account_id, contact_id)');
    }
};
