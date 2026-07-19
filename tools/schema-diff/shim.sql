-- Stand-ins mínimos de la superficie gestionada de Supabase, lo justo para
-- que supabase/migrations/*.sql aplique sobre un Postgres stock (pgvector).
-- Solo existe para el script de diff de esquema (issue #38); nada de esto
-- forma parte del esquema destino.

DO $$ BEGIN CREATE ROLE anon NOLOGIN; EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN CREATE ROLE authenticated NOLOGIN; EXCEPTION WHEN duplicate_object THEN NULL; END $$;
DO $$ BEGIN CREATE ROLE service_role NOLOGIN; EXCEPTION WHEN duplicate_object THEN NULL; END $$;
-- Varias migraciones hacen ALTER FUNCTION ... OWNER TO postgres y los
-- data-fixes SECURITY DEFINER se ejecutan como ese rol: superuser, como en
-- el Postgres gestionado de Supabase.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'postgres') THEN
        CREATE ROLE postgres NOLOGIN;
    END IF;
    ALTER ROLE postgres SUPERUSER;
END $$;

CREATE SCHEMA IF NOT EXISTS auth;
CREATE SCHEMA IF NOT EXISTS storage;

-- Lo que las migraciones leen de auth.users: id, email, raw_user_meta_data.
CREATE TABLE auth.users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email text,
    raw_user_meta_data jsonb NOT NULL DEFAULT '{}'::jsonb
);

-- En Supabase devuelve el usuario del JWT; aquí solo debe existir para que
-- las RLS/policies que lo referencian compilen.
CREATE FUNCTION auth.uid() RETURNS uuid
    LANGUAGE sql STABLE AS 'SELECT NULL::uuid';

CREATE TABLE storage.buckets (
    id text PRIMARY KEY,
    name text NOT NULL,
    public boolean NOT NULL DEFAULT false,
    file_size_limit bigint,
    allowed_mime_types text[]
);

CREATE TABLE storage.objects (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    bucket_id text REFERENCES storage.buckets (id),
    name text,
    owner uuid,
    metadata jsonb
);

-- Supabase: segmentos de carpeta del path de un objeto (sin el archivo).
CREATE FUNCTION storage.foldername(name text) RETURNS text[]
    LANGUAGE sql IMMUTABLE AS
    $$ SELECT (string_to_array(name, '/'))[1:cardinality(string_to_array(name, '/')) - 1] $$;

CREATE PUBLICATION supabase_realtime;
