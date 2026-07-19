<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * deals: estado final de Supabase (001 + 002 assigned_to y status
     * open/won/lost + 004 contact_id SET NULL para no romper historial +
     * 017 account_id). `assigned_to` referenciaba profiles(id); aquí users.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE deals (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    pipeline_id uuid NOT NULL REFERENCES pipelines(id) ON DELETE CASCADE,
                    stage_id uuid NOT NULL REFERENCES pipeline_stages(id),
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    conversation_id uuid REFERENCES conversations(id),
                    assigned_to bigint REFERENCES users(id) ON DELETE SET NULL,
                    title text NOT NULL,
                    value numeric(12,2) NOT NULL DEFAULT 0,
                    currency text DEFAULT 'USD',
                    notes text,
                    expected_close_date date,
                    status text DEFAULT 'open',
                    created_at timestamptz DEFAULT now(),
                    updated_at timestamptz DEFAULT now(),
                    CONSTRAINT deals_status_check CHECK (status IN ('open', 'won', 'lost'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_deals_pipeline ON deals(pipeline_id)');
            DB::statement('CREATE INDEX idx_deals_stage ON deals(stage_id)');
            DB::statement('CREATE INDEX idx_deals_assigned_to ON deals(assigned_to)');
            DB::statement('CREATE INDEX idx_deals_account ON deals(account_id)');

            return;
        }

        Schema::create('deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stage_id')->constrained('pipeline_stages');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('title');
            $table->decimal('value', 12, 2)->default(0);
            $table->text('currency')->nullable()->default('USD');
            $table->text('notes')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->text('status')->nullable()->default('open');
            $table->timestampsTz();

            $table->index('pipeline_id');
            $table->index('stage_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
