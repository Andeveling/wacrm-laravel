<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * broadcasts + broadcast_recipients: estado final de Supabase (001 +
     * 003 whatsapp_message_id/índices + 004 contact_id SET NULL + 005
     * trigger incremental + 017 account_id). El trío _bcast_bump /
     * _bcast_cols_for_status / broadcast_recipient_aggregate_trigger +
     * recompute_broadcast_counts es el único SQL vivo que se conserva
     * de las 36 migraciones (inventario #2); va verbatim de 005 y solo
     * en pgsql — sqlite (tests) no lleva trigger, el comportamiento se
     * verifica con tools/schema-diff.sh y llegará test de comportamiento
     * con el módulo Difusiones (#33).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE TABLE broadcasts (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                    name text NOT NULL,
                    template_name text NOT NULL,
                    template_language text NOT NULL DEFAULT 'en_US',
                    template_variables jsonb,
                    audience_filter jsonb,
                    scheduled_at timestamptz,
                    status text NOT NULL DEFAULT 'draft',
                    total_recipients integer DEFAULT 0,
                    sent_count integer DEFAULT 0,
                    delivered_count integer DEFAULT 0,
                    read_count integer DEFAULT 0,
                    replied_count integer DEFAULT 0,
                    failed_count integer DEFAULT 0,
                    created_at timestamptz DEFAULT now(),
                    updated_at timestamptz DEFAULT now(),
                    CONSTRAINT broadcasts_status_check CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_broadcasts_account ON broadcasts(account_id)');

            DB::statement(<<<'SQL'
                CREATE TABLE broadcast_recipients (
                    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                    broadcast_id uuid NOT NULL REFERENCES broadcasts(id) ON DELETE CASCADE,
                    contact_id uuid REFERENCES contacts(id) ON DELETE SET NULL,
                    status text NOT NULL DEFAULT 'pending',
                    sent_at timestamptz,
                    delivered_at timestamptz,
                    read_at timestamptz,
                    replied_at timestamptz,
                    error_message text,
                    whatsapp_message_id text,
                    created_at timestamptz DEFAULT now(),
                    CONSTRAINT broadcast_recipients_status_check CHECK (status IN ('pending', 'sent', 'delivered', 'read', 'replied', 'failed'))
                )
            SQL);
            DB::statement('CREATE INDEX idx_broadcast_recipients_broadcast ON broadcast_recipients(broadcast_id)');
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX idx_broadcast_recipients_wamid
                    ON broadcast_recipients (whatsapp_message_id)
                    WHERE whatsapp_message_id IS NOT NULL
            SQL);
            DB::statement('CREATE INDEX idx_broadcast_recipients_broadcast_status ON broadcast_recipients (broadcast_id, status)');

            $this->createAggregateTrigger();

            return;
        }

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('template_name');
            $table->text('template_language')->default('en_US');
            $table->jsonb('template_variables')->nullable();
            $table->jsonb('audience_filter')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->text('status')->default('draft');
            $table->integer('total_recipients')->nullable()->default(0);
            $table->integer('sent_count')->nullable()->default(0);
            $table->integer('delivered_count')->nullable()->default(0);
            $table->integer('read_count')->nullable()->default(0);
            $table->integer('replied_count')->nullable()->default(0);
            $table->integer('failed_count')->nullable()->default(0);
            $table->timestampsTz();

            $table->index('account_id');
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->text('status')->default('pending');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('replied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->text('whatsapp_message_id')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('broadcast_id');
            $table->index(['broadcast_id', 'status']);
        });

        // Mismo backstop de correlación de webhooks que en pgsql: sqlite
        // soporta índices únicos parciales con la misma sintaxis.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX idx_broadcast_recipients_wamid
                ON broadcast_recipients (whatsapp_message_id)
                WHERE whatsapp_message_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS public.broadcast_recipient_aggregate_trigger()');
            DB::statement('DROP FUNCTION IF EXISTS public._bcast_cols_for_status(TEXT)');
            DB::statement('DROP FUNCTION IF EXISTS public._bcast_bump(UUID, TEXT, INT)');
            DB::statement('DROP FUNCTION IF EXISTS public.recompute_broadcast_counts(UUID)');
        }
    }

    /**
     * Trío de contadores incrementales + recompute de reparación,
     * verbatim de supabase/migrations/005_broadcast_counts_incremental.sql.
     * Modelo semántico: escalera forward-only — sent_count = recipients
     * en o después de 'sent', etc.; 'failed' solo suma failed_count.
     */
    private function createAggregateTrigger(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public._bcast_bump(bid UUID, col TEXT, delta INT)
            RETURNS VOID AS $$
            BEGIN
              EXECUTE format(
                'UPDATE broadcasts SET %I = GREATEST(0, %I + $1), updated_at = NOW() WHERE id = $2',
                col, col
              ) USING delta, bid;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public._bcast_cols_for_status(s TEXT)
            RETURNS TEXT[] AS $$
            BEGIN
              -- 'pending' contributes to nothing.
              IF s = 'pending' THEN RETURN ARRAY[]::TEXT[]; END IF;
              IF s = 'sent'      THEN RETURN ARRAY['sent_count']; END IF;
              IF s = 'delivered' THEN RETURN ARRAY['sent_count','delivered_count']; END IF;
              IF s = 'read'      THEN RETURN ARRAY['sent_count','delivered_count','read_count']; END IF;
              IF s = 'replied'   THEN RETURN ARRAY['sent_count','delivered_count','read_count','replied_count']; END IF;
              IF s = 'failed'    THEN RETURN ARRAY['failed_count']; END IF;
              RETURN ARRAY[]::TEXT[];
            END;
            $$ LANGUAGE plpgsql IMMUTABLE
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.broadcast_recipient_aggregate_trigger()
            RETURNS TRIGGER AS $$
            DECLARE
              old_cols TEXT[];
              new_cols TEXT[];
              c TEXT;
            BEGIN
              IF TG_OP = 'INSERT' THEN
                new_cols := _bcast_cols_for_status(NEW.status);
                FOREACH c IN ARRAY new_cols LOOP
                  PERFORM _bcast_bump(NEW.broadcast_id, c, 1);
                END LOOP;
                RETURN NEW;
              END IF;

              IF TG_OP = 'DELETE' THEN
                old_cols := _bcast_cols_for_status(OLD.status);
                FOREACH c IN ARRAY old_cols LOOP
                  PERFORM _bcast_bump(OLD.broadcast_id, c, -1);
                END LOOP;
                RETURN OLD;
              END IF;

              -- UPDATE: only care if status changed.
              IF OLD.status IS DISTINCT FROM NEW.status THEN
                old_cols := _bcast_cols_for_status(OLD.status);
                new_cols := _bcast_cols_for_status(NEW.status);
                -- Subtract the old contributions, add the new.
                FOREACH c IN ARRAY old_cols LOOP
                  PERFORM _bcast_bump(NEW.broadcast_id, c, -1);
                END LOOP;
                FOREACH c IN ARRAY new_cols LOOP
                  PERFORM _bcast_bump(NEW.broadcast_id, c, 1);
                END LOOP;
              END IF;
              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER broadcast_recipients_aggregate
            AFTER INSERT OR UPDATE OR DELETE ON broadcast_recipients
            FOR EACH ROW EXECUTE FUNCTION public.broadcast_recipient_aggregate_trigger()
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.recompute_broadcast_counts(bid UUID)
            RETURNS VOID AS $$
            BEGIN
              UPDATE broadcasts b SET
                sent_count      = agg.sent_count,
                delivered_count = agg.delivered_count,
                read_count      = agg.read_count,
                replied_count   = agg.replied_count,
                failed_count    = agg.failed_count,
                updated_at      = NOW()
              FROM (
                SELECT
                  COUNT(*) FILTER (WHERE status IN ('sent','delivered','read','replied')) AS sent_count,
                  COUNT(*) FILTER (WHERE status IN ('delivered','read','replied'))        AS delivered_count,
                  COUNT(*) FILTER (WHERE status IN ('read','replied'))                    AS read_count,
                  COUNT(*) FILTER (WHERE status = 'replied')                              AS replied_count,
                  COUNT(*) FILTER (WHERE status = 'failed')                               AS failed_count
                FROM broadcast_recipients
                WHERE broadcast_id = bid
              ) agg
              WHERE b.id = bid;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public
        SQL);
    }
};
