<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * messages + message_reactions: estado final de Supabase (001 + 009
     * reply/reactions + 010 content_type 'interactive' e interactive_reply_id +
     * 033 ai_generated + 035 interactive_payload).
     *
     * `message_id` (id de Meta) es deliberadamente NO único: los ids de Meta
     * no son únicos entre números. `reply_to_message_id` es self-FK SET NULL.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE messages (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    conversation_id uuid NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                    sender_type text NOT NULL CHECK (sender_type IN ('customer', 'agent', 'bot')),
                    sender_id bigint,
                    content_type text NOT NULL DEFAULT 'text' CHECK (content_type IN ('text', 'image', 'document', 'audio', 'video', 'location', 'template', 'interactive')),
                    content_text text,
                    media_url text,
                    template_name text,
                    message_id text,
                    status text NOT NULL DEFAULT 'sent' CHECK (status IN ('sending', 'sent', 'delivered', 'read', 'failed')),
                    reply_to_message_id uuid REFERENCES messages(id) ON DELETE SET NULL,
                    interactive_reply_id text,
                    interactive_payload jsonb,
                    ai_generated boolean NOT NULL DEFAULT false,
                    created_at timestamptz DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_messages_conversation ON messages(conversation_id)');
            DB::statement('CREATE INDEX idx_messages_message_id ON messages(message_id)');
            DB::statement(<<<'SQL'
                CREATE INDEX idx_messages_reply_to
                    ON messages(reply_to_message_id)
                    WHERE reply_to_message_id IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                CREATE TABLE message_reactions (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    message_id uuid NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
                    conversation_id uuid NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
                    actor_type text NOT NULL CHECK (actor_type IN ('customer', 'agent')),
                    actor_id bigint,
                    emoji text NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    UNIQUE (message_id, actor_type, actor_id)
                )
            SQL);
            DB::statement('CREATE INDEX idx_message_reactions_conversation ON message_reactions(conversation_id)');
            DB::statement('CREATE INDEX idx_message_reactions_message ON message_reactions(message_id)');

            return;
        }

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->text('sender_type');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('content_type')->default('text');
            $table->text('content_text')->nullable();
            $table->text('media_url')->nullable();
            $table->text('template_name')->nullable();
            $table->text('message_id')->nullable();
            $table->text('status')->default('sent');
            $table->foreignUuid('reply_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->text('interactive_reply_id')->nullable();
            $table->jsonb('interactive_payload')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->timestampTz('created_at')->nullable();

            $table->index('conversation_id');
            $table->index('message_id');
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->text('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('emoji');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['message_id', 'actor_type', 'actor_id']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
    }
};
