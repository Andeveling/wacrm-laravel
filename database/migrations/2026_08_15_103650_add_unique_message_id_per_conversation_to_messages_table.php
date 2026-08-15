<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX idx_messages_conversation_message_id '
            .'ON messages (conversation_id, message_id) WHERE message_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_messages_conversation_message_id');
    }
};
