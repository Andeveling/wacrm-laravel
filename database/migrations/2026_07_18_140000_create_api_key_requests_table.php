<?php

use App\Support\ApiKeyRequestPartitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log of every request an API key makes. Partitioned monthly by
     * `created_at` on Postgres — the table is expected to be write-heavy and
     * the retention command (`api-keys:prune-audit`) needs cheap deletes.
     * `PARTITION BY RANGE` is Postgres-only syntax, and the partition key
     * must be part of the primary key, so this branches on the driver:
     * sqlite (used in tests) gets a plain table with the same columns.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->createPartitioned();
        } else {
            $this->createPlain();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_requests');
    }

    private function createPartitioned(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE api_key_requests (
                id bigserial NOT NULL,
                api_key_id uuid NOT NULL REFERENCES api_keys (id) ON DELETE CASCADE,
                account_id uuid NOT NULL REFERENCES accounts (id) ON DELETE CASCADE,
                method varchar(10) NOT NULL,
                path varchar(255) NOT NULL,
                status smallint NOT NULL,
                ip varchar(45),
                user_agent varchar(255),
                request_id varchar(64),
                duration_ms integer NOT NULL,
                scope_used varchar(64),
                created_at timestamp(0) without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        DB::statement('CREATE TABLE api_key_requests_default PARTITION OF api_key_requests DEFAULT');
        DB::statement('CREATE INDEX api_key_requests_api_key_id_idx ON api_key_requests (api_key_id)');
        DB::statement('CREATE INDEX api_key_requests_account_id_created_at_idx ON api_key_requests (account_id, created_at)');

        ApiKeyRequestPartitions::ensure(now());
        ApiKeyRequestPartitions::ensure(now()->addMonthNoOverflow());
    }

    private function createPlain(): void
    {
        Schema::create('api_key_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('api_key_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path');
            $table->smallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('scope_used', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('api_key_id');
            $table->index(['account_id', 'created_at']);
        });
    }
};
