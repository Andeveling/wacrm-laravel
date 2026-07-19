<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * flows + nodes + runs + run_events: estado final de Supabase (010 +
     * 016 node_type 'send_media' + 017 account_id e idempotencia por
     * cuenta + 020 índice de dispatch). `increment_flow_execution_count`
     * (012) pasa a PHP (inventario #2). El UNIQUE parcial
     * idx_one_active_run_per_contact es el linchpin de concurrencia del
     * runner: a lo sumo un run activo por (account_id, contact_id); dos
     * webhooks concurrentes colisionan en 23505 y el segundo se descarta.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE flows (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    description text,
                    status text NOT NULL DEFAULT 'draft',
                    trigger_type text NOT NULL,
                    trigger_config jsonb NOT NULL DEFAULT '{}'::jsonb,
                    entry_node_id text,
                    fallback_policy jsonb NOT NULL DEFAULT '{"on_unknown_reply":"reprompt","max_reprompts":2,"on_timeout_hours":24,"on_exhaust":"handoff"}'::jsonb,
                    execution_count integer NOT NULL DEFAULT 0,
                    last_executed_at timestamptz,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    updated_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT flows_status_check CHECK (status IN ('draft', 'active', 'archived')),
                    CONSTRAINT flows_trigger_type_check CHECK (trigger_type IN ('keyword', 'first_inbound_message', 'manual'))
                )
            SQL);
            DB::statement("CREATE INDEX idx_flows_active_trigger ON flows(user_id, trigger_type) WHERE status = 'active'");
            DB::statement('CREATE INDEX idx_flows_account ON flows(account_id)');
            DB::statement("CREATE INDEX idx_flows_account_active ON flows(account_id) WHERE status = 'active'");

            DB::statement(<<<'SQL'
                CREATE TABLE flow_nodes (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    flow_id uuid NOT NULL REFERENCES flows(id) ON DELETE CASCADE,
                    node_key text NOT NULL,
                    node_type text NOT NULL,
                    config jsonb NOT NULL DEFAULT '{}'::jsonb,
                    position_x integer NOT NULL DEFAULT 0,
                    position_y integer NOT NULL DEFAULT 0,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    UNIQUE (flow_id, node_key),
                    CONSTRAINT flow_nodes_node_type_check CHECK (node_type IN (
                        'start',
                        'send_buttons',
                        'send_list',
                        'send_message',
                        'send_media',
                        'collect_input',
                        'condition',
                        'set_tag',
                        'handoff',
                        'http_fetch',
                        'end'
                    ))
                )
            SQL);
            DB::statement('CREATE INDEX idx_flow_nodes_flow ON flow_nodes(flow_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE flow_runs (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    flow_id uuid NOT NULL REFERENCES flows(id) ON DELETE CASCADE,
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    conversation_id uuid REFERENCES conversations(id) ON DELETE SET NULL,
                    status text NOT NULL DEFAULT 'active',
                    current_node_key text,
                    last_prompt_message_id uuid REFERENCES messages(id) ON DELETE SET NULL,
                    vars jsonb NOT NULL DEFAULT '{}'::jsonb,
                    reprompt_count integer NOT NULL DEFAULT 0,
                    started_at timestamptz NOT NULL DEFAULT now(),
                    last_advanced_at timestamptz NOT NULL DEFAULT now(),
                    ended_at timestamptz,
                    end_reason text,
                    CONSTRAINT flow_runs_status_check CHECK (status IN (
                        'active',
                        'completed',
                        'handed_off',
                        'timed_out',
                        'paused_by_agent',
                        'failed'
                    ))
                )
            SQL);
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX idx_one_active_run_per_contact
                    ON flow_runs(account_id, contact_id)
                    WHERE status = 'active'
            SQL);
            DB::statement("CREATE INDEX idx_flow_runs_active_advanced ON flow_runs(last_advanced_at) WHERE status = 'active'");
            DB::statement('CREATE INDEX idx_flow_runs_flow_started ON flow_runs(flow_id, started_at DESC)');
            DB::statement('CREATE INDEX idx_flow_runs_account ON flow_runs(account_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE flow_run_events (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    flow_run_id uuid NOT NULL REFERENCES flow_runs(id) ON DELETE CASCADE,
                    event_type text NOT NULL,
                    node_key text,
                    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    CONSTRAINT flow_run_events_event_type_check CHECK (event_type IN (
                        'started',
                        'node_entered',
                        'message_sent',
                        'reply_received',
                        'fallback_fired',
                        'handoff',
                        'timeout',
                        'error',
                        'completed'
                    ))
                )
            SQL);
            DB::statement('CREATE INDEX idx_flow_run_events_run_type ON flow_run_events(flow_run_id, event_type)');
            DB::statement('CREATE INDEX idx_flow_run_events_run_time ON flow_run_events(flow_run_id, created_at DESC)');

            return;
        }

        Schema::create('flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('description')->nullable();
            $table->text('status')->default('draft');
            $table->text('trigger_type');
            $table->jsonb('trigger_config')->default('{}');
            $table->text('entry_node_id')->nullable();
            $table->jsonb('fallback_policy')->default('{"on_unknown_reply":"reprompt","max_reprompts":2,"on_timeout_hours":24,"on_exhaust":"handoff"}');
            $table->integer('execution_count')->default(0);
            $table->timestampTz('last_executed_at')->nullable();
            $table->timestampsTz();

            $table->index('account_id');
        });

        Schema::create('flow_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_id')->constrained()->cascadeOnDelete();
            $table->text('node_key');
            $table->text('node_type');
            $table->jsonb('config')->default('{}');
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->timestampTz('created_at')->nullable();

            $table->unique(['flow_id', 'node_key']);
        });

        Schema::create('flow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('status')->default('active');
            $table->text('current_node_key')->nullable();
            $table->foreignUuid('last_prompt_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->jsonb('vars')->default('{}');
            $table->integer('reprompt_count')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('last_advanced_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->text('end_reason')->nullable();

            $table->index(['flow_id', 'started_at']);
            $table->index('account_id');
        });

        // Mismo invariante de idempotencia que en pgsql (un run activo por
        // contacto por cuenta) — sqlite soporta índices únicos parciales.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX idx_one_active_run_per_contact
                ON flow_runs(account_id, contact_id)
                WHERE status = 'active'
        SQL);

        Schema::create('flow_run_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('flow_run_id')->constrained('flow_runs')->cascadeOnDelete();
            $table->text('event_type');
            $table->text('node_key')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('created_at')->nullable();

            $table->index(['flow_run_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_run_events');
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flow_nodes');
        Schema::dropIfExists('flows');
    }
};
