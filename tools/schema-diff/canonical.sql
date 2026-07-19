-- Listado canónico y diffeable del schema `public`: mismas consultas sobre
-- ambas bases, salida ordenada, con los nombres que Postgres autogenera
-- (constraints/índices) reemplazados por `~` para que solo pese la
-- definición. Policies y grants quedan fuera a propósito: la RLS se elimina
-- en Laravel por decisión del mapa (#2/#9).

SELECT 'extension|' || extname
FROM pg_extension
WHERE extname <> 'plpgsql'
ORDER BY 1;

SELECT 'table|' || c.relname
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND NOT c.relispartition
ORDER BY 1;

SELECT 'column|' || c.relname || '|' || a.attname
    || '|' || format_type(a.atttypid, a.atttypmod)
    || '|' || CASE WHEN a.attnotnull THEN 'not null' ELSE 'null' END
    || '|' || COALESCE(pg_get_expr(d.adbin, d.adrelid), '-')
    || '|' || CASE a.attgenerated WHEN 's' THEN 'generated' ELSE '-' END
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
LEFT JOIN pg_attrdef d ON d.adrelid = c.oid AND d.adnum = a.attnum
WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p') AND NOT c.relispartition
ORDER BY 1;

SELECT 'constraint|' || rel.relname || '|' || pg_get_constraintdef(con.oid)
FROM pg_constraint con
JOIN pg_class rel ON rel.oid = con.conrelid
JOIN pg_namespace n ON n.oid = rel.relnamespace
WHERE n.nspname = 'public' AND NOT rel.relispartition
ORDER BY 1;

SELECT 'index|' || t.relname || '|'
    || regexp_replace(pg_get_indexdef(i.indexrelid), ' INDEX [^ ]+ ON ', ' INDEX ~ ON ')
FROM pg_index i
JOIN pg_class t ON t.oid = i.indrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE n.nspname = 'public' AND NOT t.relispartition
    AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid)
ORDER BY 1;

SELECT 'trigger|' || t.relname || '|' || pg_get_triggerdef(g.oid)
FROM pg_trigger g
JOIN pg_class t ON t.oid = g.tgrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE n.nspname = 'public' AND NOT g.tgisinternal
ORDER BY 1;

SELECT 'function|' || p.proname || '(' || pg_get_function_identity_arguments(p.oid) || ')'
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
    AND NOT EXISTS (
        SELECT 1 FROM pg_depend dep
        WHERE dep.objid = p.oid AND dep.deptype = 'e'
    )
ORDER BY 1;
