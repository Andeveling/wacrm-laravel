<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * conversations: estado final de Supabase (001 + 017 account_id +
     * 029/033 columnas de auto-reply IA + 036 UNIQUE (account, contact)).
     * `assigned_agent_id` no lleva FK, igual que en Supabase.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE conversations (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    contact_id uuid NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
                    status text NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'pending', 'closed')),
                    assigned_agent_id bigint,
                    last_message_text text,
                    last_message_at timestamptz,
                    unread_count integer DEFAULT 0,
                    ai_autoreply_disabled boolean NOT NULL DEFAULT false,
                    ai_reply_count integer NOT NULL DEFAULT 0,
                    ai_handoff_summary text,
                    created_at timestamptz DEFAULT now(),
                    updated_at timestamptz DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_conversations_user_id ON conversations(user_id)');
            DB::statement('CREATE INDEX idx_conversations_contact_id ON conversations(contact_id)');
            DB::statement('CREATE INDEX idx_conversations_account ON conversations(account_id)');
        } else {
            Schema::create('conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
                $table->text('status')->default('open');
                $table->unsignedBigInteger('assigned_agent_id')->nullable();
                $table->text('last_message_text')->nullable();
                $table->timestampTz('last_message_at')->nullable();
                $table->integer('unread_count')->default(0);
                $table->boolean('ai_autoreply_disabled')->default(false);
                $table->integer('ai_reply_count')->default(0);
                $table->text('ai_handoff_summary')->nullable();
                $table->timestampsTz();

                $table->index('contact_id');
                $table->index('account_id');
            });
        }

        // Invariante 036: una conversación por contacto por cuenta; backstop
        // 23505 del webhook. Mismo SQL en ambos drivers.
        DB::statement('CREATE UNIQUE INDEX idx_conversations_account_contact ON conversations (account_id, contact_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
