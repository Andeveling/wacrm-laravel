<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * quick_replies (035): snippets reusables del composer, texto plano o
     * mensaje interactivo guardado. `user_id` es autor/auditoría, la
     * tenencia es por account_id. En Supabase el id usaba uuid_generate_v4();
     * aquí gen_random_uuid() (desviación documentada).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE quick_replies (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    title text NOT NULL,
                    kind text NOT NULL DEFAULT 'text' CHECK (kind IN ('text', 'interactive')),
                    content_text text,
                    interactive_payload jsonb,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    updated_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_quick_replies_account ON quick_replies(account_id)');

            return;
        }

        Schema::create('quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('title');
            $table->text('kind')->default('text');
            $table->text('content_text')->nullable();
            $table->jsonb('interactive_payload')->nullable();
            $table->timestampsTz();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_replies');
    }
};
