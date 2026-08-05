<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * automations + steps + logs + pending_executions: estado final de
     * Supabase (006 + 017 account_id + 020 índice de dispatch por cuenta).
     * `increment_automation_execution_count` (007) pasa a PHP (inventario
     * #2); el trigger set_updated_at lo cubre Eloquent.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE automations (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    description text,
                    trigger_type text NOT NULL,
                    trigger_config jsonb NOT NULL DEFAULT '{}'::jsonb,
                    is_active boolean NOT NULL DEFAULT FALSE,
                    execution_count integer NOT NULL DEFAULT 0,
                    last_executed_at timestamptz,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    updated_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX idx_automations_user_id ON automations(user_id)');
            DB::statement('CREATE INDEX idx_automations_active_trigger ON automations(trigger_type) WHERE is_active = TRUE');
            DB::statement('CREATE INDEX idx_automations_account ON automations(account_id)');
            DB::statement('CREATE INDEX idx_automations_account_active_trigger ON automations(account_id, trigger_type) WHERE is_active = TRUE');

            DB::statement(<<<'SQL'
                CREATE TABLE automation_steps (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    automation_id uuid NOT NULL REFERENCES automations(id) ON DELETE CASCADE,
                    parent_step_id uuid REFERENCES automation_steps(id) ON DELETE CASCADE,
                    branch text,
                    step_type text NOT NULL,
                    step_config jsonb NOT NULL DEFAULT '{}'::jsonb,
                    position integer NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT automation_steps_branch_check CHECK (branch IN ('yes', 'no'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_automation_steps_automation_id ON automation_steps(automation_id, position)');
            DB::statement('CREATE INDEX idx_automation_steps_parent ON automation_steps(parent_step_id) WHERE parent_step_id IS NOT NULL');

            DB::statement(<<<'SQL'
                CREATE TABLE automation_logs (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    automation_id uuid NOT NULL REFERENCES automations(id) ON DELETE CASCADE,
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    trigger_event text NOT NULL,
                    steps_executed jsonb NOT NULL DEFAULT '[]'::jsonb,
                    status text NOT NULL,
                    error_message text,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT automation_logs_status_check CHECK (status IN ('success', 'partial', 'failed'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_automation_logs_automation ON automation_logs(automation_id, created_at DESC)');
            DB::statement('CREATE INDEX idx_automation_logs_user ON automation_logs(user_id)');
            DB::statement('CREATE INDEX idx_automation_logs_account ON automation_logs(account_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE automation_pending_executions (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    automation_id uuid NOT NULL REFERENCES automations(id) ON DELETE CASCADE,
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    log_id uuid REFERENCES automation_logs(id) ON DELETE CASCADE,
                    parent_step_id uuid REFERENCES automation_steps(id) ON DELETE SET NULL,
                    branch text,
                    next_step_position integer NOT NULL,
                    context jsonb NOT NULL DEFAULT '{}'::jsonb,
                    status text NOT NULL DEFAULT 'pending',
                    run_at timestamptz NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT automation_pending_executions_branch_check CHECK (branch IN ('yes', 'no')),
                    CONSTRAINT automation_pending_executions_status_check CHECK (status IN ('pending', 'running', 'done', 'failed'))
                )
            SQL);
            DB::statement("CREATE INDEX idx_automation_pending_due ON automation_pending_executions(run_at) WHERE status = 'pending'");
            DB::statement('CREATE INDEX idx_automation_pending_account ON automation_pending_executions(account_id)');

            return;
        }

        Schema::create('automations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('trigger_type');
            $table->jsonb('trigger_config')->default('{}');
            $table->boolean('is_active')->default(false);
            $table->integer('execution_count')->default(0);
            $table->timestampTz('last_executed_at')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
            $table->index('account_id');
            $table->index(['account_id', 'trigger_type']);
        });

        Schema::create('automation_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_step_id')->nullable()->constrained('automation_steps')->cascadeOnDelete();
            $table->text('branch')->nullable();
            $table->text('step_type');
            $table->jsonb('step_config')->default('{}');
            $table->integer('position');
            $table->timestampTz('created_at')->nullable();

            $table->index(['automation_id', 'position']);
        });

        Schema::create('automation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->text('trigger_event');
            $table->jsonb('steps_executed')->default('[]');
            $table->text('status');
            $table->text('error_message')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['automation_id', 'created_at']);
            $table->index('account_id');
        });

        Schema::create('automation_pending_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('log_id')->nullable()->constrained('automation_logs')->cascadeOnDelete();
            $table->foreignUuid('parent_step_id')->nullable()->constrained('automation_steps')->nullOnDelete();
            $table->text('branch')->nullable();
            $table->integer('next_step_position');
            $table->jsonb('context')->default('{}');
            $table->text('status')->default('pending');
            $table->timestampTz('run_at');
            $table->timestampTz('created_at')->nullable();

            $table->index('run_at');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_pending_executions');
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_steps');
        Schema::dropIfExists('automations');
    }
};
