<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * notifications (027): buzón in-app por usuario receptor. El trigger
     * notify_conversation_assigned de Supabase pasa a PHP (Observer +
     * broadcast, ticket aparte); la RLS de "solo read_at" pasa a Policy.
     * En Supabase el id usaba uuid_generate_v4(); aquí gen_random_uuid()
     * (desviación documentada).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE notifications (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    type text NOT NULL DEFAULT 'conversation_assigned' CHECK (type IN ('conversation_assigned')),
                    conversation_id uuid REFERENCES conversations(id) ON DELETE CASCADE,
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    actor_user_id bigint REFERENCES users(id) ON DELETE SET NULL,
                    title text NOT NULL,
                    body text,
                    read_at timestamptz,
                    created_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at DESC)');
            DB::statement(<<<'SQL'
                CREATE INDEX idx_notifications_user_unread
                    ON notifications(user_id)
                    WHERE read_at IS NULL
            SQL);

            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('type')->default('conversation_assigned');
            $table->foreignUuid('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('title');
            $table->text('body')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
