<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_number_connections', function (Blueprint $table): void {
            $table->boolean('pending_default')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_number_connections', function (Blueprint $table): void {
            $table->dropColumn('pending_default');
        });
    }
};
