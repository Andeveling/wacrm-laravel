<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->foreignUuid('connection_id')->nullable()->after('account_id');
            $table->index('connection_id');
            $table->foreign(['account_id', 'connection_id'])
                ->references(['account_id', 'id'])
                ->on('whatsapp_phone_number_connections')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropForeign(['account_id', 'connection_id']);
            $table->dropColumn('connection_id');
        });
    }
};
