<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ai_configs + ai_knowledge_documents + ai_knowledge_chunks +
     * ai_usage_log: estado final de Supabase (029 + 030 embeddings_api_key
     * y knowledge + 033 handoff_agent_id y usage log). Los RPCs
     * (claim_ai_reply_slot, match_ai_knowledge_*) pasan a PHP en #37
     * sobre laravel/ai (inventario #2, decisión en #37).
     *
     * En pgsql, chunks lleva el par de recuperación del doc de producto:
     * fts tsvector GENERATED ('simple': tokeniza sin stemming inglés,
     * degrada bien en cualquier idioma) + embedding vector(1536)
     * (text-embedding-3-small) con HNSW — sin entrenamiento, precisión
     * desde la primera fila, a diferencia de IVFFlat. sqlite (tests)
     * omite fts y deja embedding como text nullable.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

            DB::statement(<<<'SQL'
                CREATE TABLE ai_configs (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL UNIQUE REFERENCES accounts(id) ON DELETE CASCADE,
                    created_by bigint REFERENCES users(id) ON DELETE SET NULL,
                    provider text NOT NULL,
                    model text NOT NULL,
                    api_key text NOT NULL,
                    system_prompt text,
                    is_active boolean NOT NULL DEFAULT false,
                    auto_reply_enabled boolean NOT NULL DEFAULT false,
                    auto_reply_max_per_conversation integer NOT NULL DEFAULT 3,
                    embeddings_api_key text,
                    handoff_agent_id bigint REFERENCES users(id) ON DELETE SET NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    updated_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT ai_configs_provider_check CHECK (provider IN ('openai', 'anthropic')),
                    CONSTRAINT ai_configs_auto_reply_max_per_conversation_check CHECK (auto_reply_max_per_conversation BETWEEN 1 AND 20)
                )
            SQL);

            DB::statement(<<<'SQL'
                CREATE TABLE ai_knowledge_documents (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    created_by bigint REFERENCES users(id) ON DELETE SET NULL,
                    title text NOT NULL,
                    content text NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    updated_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX ai_knowledge_documents_account_id_idx ON ai_knowledge_documents (account_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE ai_knowledge_chunks (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    document_id uuid NOT NULL REFERENCES ai_knowledge_documents(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    chunk_index integer NOT NULL DEFAULT 0,
                    content text NOT NULL,
                    fts tsvector GENERATED ALWAYS AS (to_tsvector('simple', content)) STORED,
                    embedding vector(1536),
                    created_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX ai_knowledge_chunks_account_id_idx ON ai_knowledge_chunks (account_id)');
            DB::statement('CREATE INDEX ai_knowledge_chunks_document_id_idx ON ai_knowledge_chunks (document_id)');
            DB::statement('CREATE INDEX ai_knowledge_chunks_fts_idx ON ai_knowledge_chunks USING gin (fts)');
            DB::statement('CREATE INDEX ai_knowledge_chunks_embedding_idx ON ai_knowledge_chunks USING hnsw (embedding vector_cosine_ops)');

            DB::statement(<<<'SQL'
                CREATE TABLE ai_usage_log (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    conversation_id uuid REFERENCES conversations(id) ON DELETE SET NULL,
                    mode text NOT NULL,
                    provider text NOT NULL,
                    model text NOT NULL,
                    prompt_tokens integer NOT NULL DEFAULT 0,
                    completion_tokens integer NOT NULL DEFAULT 0,
                    total_tokens integer NOT NULL DEFAULT 0,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT ai_usage_log_mode_check CHECK (mode IN ('auto_reply', 'draft')),
                    CONSTRAINT ai_usage_log_provider_check CHECK (provider IN ('openai', 'anthropic'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_ai_usage_log_account_created ON ai_usage_log(account_id, created_at DESC)');

            return;
        }

        Schema::create('ai_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('provider');
            $table->text('model');
            $table->text('api_key');
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->integer('auto_reply_max_per_conversation')->default(3);
            $table->text('embeddings_api_key')->nullable();
            $table->foreignId('handoff_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('title');
            $table->text('content');
            $table->timestampsTz();

            $table->index('account_id');
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('ai_knowledge_documents')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->text('embedding')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
            $table->index('document_id');
        });

        Schema::create('ai_usage_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('mode');
            $table->text('provider');
            $table->text('model');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->timestampTz('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_configs');
    }
};
