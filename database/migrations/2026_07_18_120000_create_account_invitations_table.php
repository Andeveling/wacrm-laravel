<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ports `account_invitations` from supabase/migrations/017_account_sharing.sql
     * verbatim. The plaintext token never lands in the DB; the route handler
     * stores only the SHA-256 hash. `invited_by` / `accepted_by` use Laravel's
     * `foreignId` against `users.id` (Supabase uses `auth.users` UUIDs).
     */
    public function up(): void
    {
        Schema::create('account_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('role');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_invitations');
    }
};
