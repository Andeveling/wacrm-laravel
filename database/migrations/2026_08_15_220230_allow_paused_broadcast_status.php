<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE broadcasts DROP CONSTRAINT broadcasts_status_check');
        DB::statement(
            "ALTER TABLE broadcasts ADD CONSTRAINT broadcasts_status_check CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed', 'paused'))",
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE broadcasts DROP CONSTRAINT broadcasts_status_check');
        DB::statement(
            "ALTER TABLE broadcasts ADD CONSTRAINT broadcasts_status_check CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed'))",
        );
    }
};
