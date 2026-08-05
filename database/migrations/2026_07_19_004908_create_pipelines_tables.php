<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pipelines + pipeline_stages: estado final de Supabase (001 + 017
     * account_id en pipelines; stages se scopean vía su pipeline).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE pipelines (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    created_at timestamptz DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_pipelines_account ON pipelines(account_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE pipeline_stages (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    pipeline_id uuid NOT NULL REFERENCES pipelines(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    position integer NOT NULL DEFAULT 0,
                    color text NOT NULL DEFAULT '#3b82f6',
                    created_at timestamptz DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_pipeline_stages_pipeline ON pipeline_stages(pipeline_id)');

            return;
        }

        Schema::create('pipelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->integer('position')->default(0);
            $table->text('color')->default('#3b82f6');
            $table->timestampTz('created_at')->nullable();

            $table->index('pipeline_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
