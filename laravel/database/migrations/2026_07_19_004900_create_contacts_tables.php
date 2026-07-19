<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dominio contactos: contacts, tags, contact_tags, custom_fields,
     * contact_custom_values, contact_notes. Estado final de Supabase
     * (001 + 017 account_id + 022 phone_normalized), con las FKs a
     * usuarios en bigint (desviación documentada en docs/schema-deviations.md).
     *
     * En pgsql el DDL es SQL crudo idéntico al de Supabase para que
     * tools/schema-diff.sh compare limpio (tipos text/timestamptz, defaults
     * now()/gen_random_uuid(), columna GENERATED). sqlite (tests) recibe el
     * equivalente en Blueprint; `phone_normalized` ahí es una columna normal
     * que el modelo Contact rellena al guardar.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->createPgsql();
        } else {
            $this->createPlain();
        }

        // Invariante anti-duplicados (022): única por cuenta la versión
        // solo-dígitos del teléfono. Mismo SQL en pgsql y sqlite.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX idx_contacts_account_phone_normalized
                ON contacts (account_id, phone_normalized)
                WHERE phone_normalized <> ''
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_notes');
        Schema::dropIfExists('contact_custom_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contacts');
    }

    private function createPgsql(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE contacts (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                phone text NOT NULL,
                name text,
                email text,
                company text,
                avatar_url text,
                phone_normalized text GENERATED ALWAYS AS (regexp_replace(phone, '\D', '', 'g')) STORED,
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )
        SQL);
        DB::statement('CREATE INDEX idx_contacts_user_id ON contacts(user_id)');
        DB::statement('CREATE INDEX idx_contacts_phone ON contacts(phone)');
        DB::statement('CREATE INDEX idx_contacts_account ON contacts(account_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE tags (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                name text NOT NULL,
                color text NOT NULL DEFAULT '#3b82f6',
                created_at timestamptz DEFAULT now()
            )
        SQL);
        DB::statement('CREATE INDEX idx_tags_account ON tags(account_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE contact_tags (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                contact_id uuid NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
                tag_id uuid NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
                created_at timestamptz DEFAULT now(),
                UNIQUE (contact_id, tag_id)
            )
        SQL);
        DB::statement('CREATE INDEX idx_contact_tags_contact ON contact_tags(contact_id)');
        DB::statement('CREATE INDEX idx_contact_tags_tag ON contact_tags(tag_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE custom_fields (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                field_name text NOT NULL,
                field_type text NOT NULL DEFAULT 'text',
                field_options jsonb,
                created_at timestamptz DEFAULT now()
            )
        SQL);
        DB::statement('CREATE INDEX idx_custom_fields_account ON custom_fields(account_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE contact_custom_values (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                contact_id uuid NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
                custom_field_id uuid NOT NULL REFERENCES custom_fields(id) ON DELETE CASCADE,
                value text,
                created_at timestamptz DEFAULT now(),
                UNIQUE (contact_id, custom_field_id)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE contact_notes (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                contact_id uuid NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
                user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                account_id uuid NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
                note_text text NOT NULL,
                created_at timestamptz DEFAULT now()
            )
        SQL);
        DB::statement('CREATE INDEX idx_contact_notes_account ON contact_notes(account_id)');
    }

    private function createPlain(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('phone');
            $table->text('name')->nullable();
            $table->text('email')->nullable();
            $table->text('company')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('phone_normalized')->nullable();
            $table->timestampsTz();

            $table->index('account_id');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('color')->default('#3b82f6');
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
        });

        Schema::create('contact_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['contact_id', 'tag_id']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('field_name');
            $table->text('field_type')->default('text');
            $table->jsonb('field_options')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
        });

        Schema::create('contact_custom_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('custom_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['contact_id', 'custom_field_id']);
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            $table->text('note_text');
            $table->timestampTz('created_at')->nullable();

            $table->index('account_id');
        });
    }
};
