<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * message_templates: estado final de Supabase (001 + 014 integración
     * Meta: enum crudo de status, columnas de submit/webhook, shape-check de
     * buttons, UNIQUE (user_id, name, language) para el upsert del sync +
     * 017 account_id).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE message_templates (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    category text NOT NULL DEFAULT 'Marketing' CHECK (category IN ('Marketing', 'Utility', 'Authentication')),
                    language text DEFAULT 'en_US',
                    header_type text CHECK (header_type IN ('text', 'image', 'video', 'document')),
                    header_content text,
                    body_text text NOT NULL,
                    footer_text text,
                    buttons jsonb,
                    status text DEFAULT 'DRAFT',
                    sample_values jsonb,
                    meta_template_id text,
                    rejection_reason text,
                    quality_score text,
                    header_handle text,
                    header_media_url text,
                    submission_error text,
                    last_submitted_at timestamptz,
                    created_at timestamptz DEFAULT now(),
                    updated_at timestamptz DEFAULT now(),
                    CONSTRAINT message_templates_status_meta_check CHECK (status IN (
                        'DRAFT',
                        'PENDING',
                        'APPROVED',
                        'REJECTED',
                        'PAUSED',
                        'DISABLED',
                        'IN_APPEAL',
                        'PENDING_DELETION'
                    )),
                    CONSTRAINT message_templates_quality_score_check
                        CHECK (quality_score IS NULL OR quality_score IN ('GREEN', 'YELLOW', 'RED')),
                    CONSTRAINT message_templates_buttons_shape_check CHECK (
                        buttons IS NULL
                        OR (
                            jsonb_typeof(buttons) = 'array'
                            AND jsonb_array_length(buttons) <= 10
                        )
                    )
                )
            SQL);
            DB::statement('CREATE INDEX idx_message_templates_account ON message_templates(account_id)');
            DB::statement(<<<'SQL'
                CREATE INDEX idx_message_templates_meta_template_id
                    ON message_templates (meta_template_id)
                    WHERE meta_template_id IS NOT NULL
            SQL);
        } else {
            Schema::create('message_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
                $table->text('name');
                $table->text('category')->default('Marketing');
                $table->text('language')->nullable()->default('en_US');
                $table->text('header_type')->nullable();
                $table->text('header_content')->nullable();
                $table->text('body_text');
                $table->text('footer_text')->nullable();
                $table->jsonb('buttons')->nullable();
                $table->text('status')->nullable()->default('DRAFT');
                $table->jsonb('sample_values')->nullable();
                $table->text('meta_template_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('quality_score')->nullable();
                $table->text('header_handle')->nullable();
                $table->text('header_media_url')->nullable();
                $table->text('submission_error')->nullable();
                $table->timestampTz('last_submitted_at')->nullable();
                $table->timestampsTz();

                $table->index('account_id');
            });
        }

        // Invariante 014: el upsert del sync con Meta matchea por
        // (user_id, name, language). Mismo SQL en ambos drivers.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX message_templates_user_name_language_key
                ON message_templates (user_id, name, language)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
