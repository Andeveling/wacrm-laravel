#!/usr/bin/env bash
# Diff de esquema contra Supabase (issue #38, spec #25).
#
# Verifica que las migraciones Laravel produzcan el mismo schema `public`
# que las 36 migraciones de Supabase: crea dos bases scratch dentro del
# pgsql de sail, aplica shim + supabase/migrations/*.sql en una y
# `artisan migrate` en la otra, genera el listado canónico de ambas
# (tools/schema-diff/canonical.sql), filtra las desviaciones aceptadas
# (tools/schema-diff/exceptions.txt, documentadas en
# docs/schema-deviations.md) y diffea.
#
# Uso: tools/schema-diff.sh   (requiere sail arriba: vendor/bin/sail up -d)
# Exit 0: sin diferencias no exceptuadas. Exit 1: hay diff (ver salida y
# artefactos en tools/schema-diff/out/).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIR="$ROOT/tools/schema-diff"
OUT="$DIR/out"
mkdir -p "$OUT"

sail() { (cd "$ROOT/laravel" && vendor/bin/sail "$@"); }
psql_scratch() { sail exec -T pgsql psql -qX -v ON_ERROR_STOP=1 -U wacrm "$@"; }

echo "==> recreando bases scratch"
psql_scratch -d wacrm \
    -c 'DROP DATABASE IF EXISTS schema_diff_supabase;' \
    -c 'DROP DATABASE IF EXISTS schema_diff_laravel;' \
    -c 'CREATE DATABASE schema_diff_supabase;' \
    -c 'CREATE DATABASE schema_diff_laravel;'

echo "==> aplicando shim + 36 migraciones supabase"
cat "$DIR/shim.sql" "$ROOT"/supabase/migrations/*.sql \
    | psql_scratch -d schema_diff_supabase >/dev/null

echo "==> corriendo migraciones laravel"
sail exec -T -e DB_DATABASE=schema_diff_laravel app \
    php artisan migrate --force --no-interaction >/dev/null

# Si el contenedor tiene config cacheada, -e DB_DATABASE se ignora y migrate
# apunta a la base real: verificar que la scratch quedó poblada.
tables=$(psql_scratch -At -d schema_diff_laravel -c \
    "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind = 'r'")
if [ "$tables" -eq 0 ]; then
    echo "ERROR: migrate no pobló schema_diff_laravel (¿config cacheada en el contenedor? corre: sail artisan config:clear)" >&2
    exit 2
fi

echo "==> generando listados canónicos"
psql_scratch -At -d schema_diff_supabase -f - <"$DIR/canonical.sql" >"$OUT/supabase.txt"
psql_scratch -At -d schema_diff_laravel -f - <"$DIR/canonical.sql" >"$OUT/laravel.txt"

# grep sale 1 si no queda ninguna línea (válido) y 2 si una regex es
# inválida — solo el 1 se tolera; un 2 silenciado daría verde falso.
filter() { grep -vEf "$OUT/patterns.txt" "$1" || [ $? -eq 1 ]; }
grep -vE '^\s*(#|$)' "$DIR/exceptions.txt" >"$OUT/patterns.txt" || [ $? -eq 1 ]
filter "$OUT/supabase.txt" >"$OUT/supabase.filtered.txt"
filter "$OUT/laravel.txt" >"$OUT/laravel.filtered.txt"

if diff -u --label supabase --label laravel \
    "$OUT/supabase.filtered.txt" "$OUT/laravel.filtered.txt" >"$OUT/diff.txt"; then
    echo "OK: esquemas iguales (desviaciones aceptadas en docs/schema-deviations.md)"
else
    echo "DIFF DE ESQUEMA (− solo en supabase / + solo en laravel); artefactos en tools/schema-diff/out/"
    cat "$OUT/diff.txt"
    exit 1
fi
