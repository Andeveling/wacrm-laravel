<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * member_presence (024): heartbeat online/away por miembro; "offline" se
     * deriva por staleness de last_seen_at, no se persiste. El RPC
     * touch_presence de Supabase pasa a un upsert Eloquent (ticket aparte).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE member_presence (
                    user_id bigint PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    status text NOT NULL DEFAULT 'online' CHECK (status IN ('online', 'away')),
                    last_seen_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX member_presence_account_idx ON member_presence(account_id)');

            return;
        }

        Schema::create('member_presence', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('status')->default('online');
            $table->timestampTz('last_seen_at')->useCurrent();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_presence');
    }
};
