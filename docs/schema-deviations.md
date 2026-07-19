# Desviaciones de esquema aceptadas (Supabase → Laravel)

Registro de las diferencias **deliberadas** entre el esquema de Supabase
(`supabase/migrations/001..036`) y el de Laravel. El script
`tools/schema-diff.sh` compara ambos esquemas y filtra estas desviaciones vía
`tools/schema-diff/exceptions.txt`; todo lo que no esté aquí es una
diferencia real que debe corregirse. Decisiones de origen: spec
[#25](https://github.com/Andeveling/wacrm/issues/25) e inventario
[#2](https://github.com/Andeveling/wacrm/issues/2).

## Tablas propias del framework Laravel

`migrations`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`,
`jobs`, `job_batches`, `failed_jobs`, `passkeys`, `api_key_requests`,
`agent_conversations`, `agent_conversation_messages`.

No existen en Supabase: infraestructura de Laravel (colas, sesiones, cache),
passkeys de Fortify, el audit log de API keys (#21) y las tablas del ai-sdk
(`laravel/ai`). Supabase resolvía estas piezas fuera de `public`
(`auth.sessions`, colas externas, etc.).

## Familia auth/tenancy rediseñada (#12–#21)

| Supabase | Laravel | Nota |
|---|---|---|
| `profiles` (uuid, espejo de `auth.users`) | `users` (bigint id) | Starter kit de Fortify; toda FK a usuarios pasa de uuid a bigint |
| `account_members` | `account_user` | Convención de pivote de Laravel |
| `accounts`, `account_invitations`, `api_keys` | ídem | Reconstruidas con tipos Laravel: `varchar(255)` en vez de `text`, `timestamp(0)` sin tz, enums PHP en vez de tipos Postgres (`account_role_enum`), columnas renombradas (`accepted_by_user_id` → `accepted_by`) |

No se migran datos reales (lo de Supabase es dummy — decisión en #25), así
que el cambio de tipos no requiere mapeo de datos.

Nota para #39/#40: las FKs a usuarios en tablas de dominio (`assigned_to`,
`created_by`, `user_id`…) heredan uuid→bigint; al portar cada tabla, sus
líneas de columna/constraint diferirán del lado Supabase y habrá que agregar
la excepción puntual aquí y en `exceptions.txt`.

## Extensión `uuid-ossp`

Laravel genera uuids con `gen_random_uuid()` (nativo de Postgres) y
`HasUuids` en Eloquent; la extensión no se instala.

## Funciones y triggers que pasan a PHP o se eliminan

Clasificación del inventario (#2):

- **Timestamps** — `update_updated_at_column()`, `update_ai_configs_updated_at()`,
  `update_ai_knowledge_documents_updated_at()` y todos los triggers
  `set_updated_at`/`*_updated_at`: los cubre `$timestamps` de Eloquent.
- **RPCs de membresía/invitaciones** — `handle_new_user`, `is_account_member`,
  `set_member_role`, `remove_account_member`, `transfer_account_ownership`,
  `peek_invitation`, `redeem_invitation`: ya portadas como Actions/Policies
  en #12–#18.
- **Defensas específicas de PostgREST** — `enforce_profile_privilege_columns`:
  sin cliente SQL directo no aplica.
- **Data-fixes one-time** — `merge_duplicate_contacts`,
  `merge_duplicate_conversations`: sin datos que migrar, sobran.
- **Pasan a PHP con su módulo** — `touch_presence` (presence nativo de
  Reverb), `filter_contacts_by_tags` (query Eloquent), `notify_conversation_assigned`
  (Observer + broadcast), `record_webhook_failure`, `claim_ai_reply_slot`
  (UPDATE atómicos del query builder), `match_ai_knowledge_fts`/`_semantic`
  (query builder en el servicio de IA), `increment_automation_execution_count`,
  `increment_flow_execution_count` (`increment()` atómico).

**Lo único que NO es desviación**: el trío de contadores de broadcasts
(`_bcast_bump`, `_bcast_cols_for_status`, `broadcast_recipient_aggregate_trigger`),
su trigger y `recompute_broadcast_counts` — deben existir en Laravel (#40) y
el diff los exige.

## RLS, policies y grants

No aparecen en el listado canónico (`tools/schema-diff/canonical.sql`) por
diseño: toda la RLS se reemplaza por `AccountScope` + Policies de Laravel
(#2/#9), y los GRANT por rol de PostgREST pierden sentido sin PostgREST.
