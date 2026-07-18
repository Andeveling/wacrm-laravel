<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ports `api_keys` from supabase/migrations/026_api_keys.sql. Account-scoped;
     * we store only the SHA-256 *hash* of the plaintext key, never plaintext. The
     * `created_by` column tracks who minted the key for audit and uses Laravel's
     * `foreignId` against `users.id` (int). Supabase used `auth.users(id)` UUID
     * with `ON DELETE SET NULL` — semantics preserved (removing the user keeps
     * keys alive so dependent automations don't break); type adapted.
     *
     * `key_prefix` is a non-secret display string (`wacrm_live_a1b2c3d4`) the
     * dashboard shows in lists. `scopes[]` is unrestricted text[] — the
     * vocabulary is enforced in the application layer (ApiScope enum).
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('key_prefix');
            $table->string('key_hash')->unique();
            $table->json('scopes');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
