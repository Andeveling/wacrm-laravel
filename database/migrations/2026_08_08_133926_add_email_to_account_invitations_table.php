<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_invitations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('role');
            $table->index(['account_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_invitations', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'email']);
            $table->dropColumn('email');
        });
    }
};
