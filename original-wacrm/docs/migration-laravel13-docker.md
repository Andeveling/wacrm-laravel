# Plan de migración: wacrm → Laravel 13 + Docker

## Por qué

Hoy el stack depende de tres servicios externos para funcionar: Supabase (DB + Auth +
Storage + Realtime), Vercel (hosting/build) y opcionalmente Hostinger. Ninguno es
reemplazable sin tocar código porque están integrados directo en `src/lib/supabase/*` y
en `src/proxy.ts`. El objetivo de esta migración es que **todo el stack corra con
`docker compose up` en cualquier VPS**, sin cuentas de terceros obligatorias.

Decisión ya tomada con el usuario:
- Frontend: **Laravel + Inertia + React** — se reutiliza casi todo `src/components/**`
  (mismo lenguaje, mismo Tailwind), Laravel controla routing/auth/datos.
- Base de datos: **PostgreSQL propio en un contenedor de docker-compose**, no Supabase
  Postgres. Imagen con `pgvector` (usa la IA/knowledge base actual con embeddings).

## Inventario del proyecto actual (lo que hay que migrar)

| Área | Tamaño | Ubicación |
|---|---|---|
| Páginas (App Router) | 28 `page.tsx` | `src/app/**` |
| Componentes React | 99 `.tsx` | `src/components/**` |
| Migraciones SQL | 36 archivos | `supabase/migrations/` |
| Motor de Automations | 757 líneas | `src/lib/automations/engine.ts` |
| Motor de Flows (visual, xyflow) | 1117 líneas | `src/lib/flows/engine.ts` |
| Cliente Meta/WhatsApp API | 1044 líneas | `src/lib/whatsapp/meta-api.ts` |
| IA (BYOK OpenAI/Anthropic + pgvector) | ~1000 líneas | `src/lib/ai/**` |
| Webhooks salientes (firma, SSRF guard) | ~460 líneas | `src/lib/webhooks/**` |
| API pública v1 (API keys) | 15 rutas | `src/app/api/v1/**` |
| Realtime (inbox, presencia, notifs) | 4 hooks | `src/hooks/use-realtime.ts`, `use-presence.ts`, etc. |
| Auth/middleware multi-tenant | `src/proxy.ts` (95 líneas) |

Todo lo demás (RLS, RPCs de Postgres, triggers de contadores) vive en las 36
migraciones SQL — hay que leerlas todas para saber qué invariantes de negocio están
hoy en la base de datos y no en el código TypeScript.

## Arquitectura destino

```
docker-compose.yml
├── app          (PHP-FPM, Laravel 13, código en /var/www)
├── nginx / caddy (reverse proxy + TLS)
├── postgres     (imagen pgvector/pgvector:pg16, volumen persistente)
├── redis        (cache, colas, broadcasting driver)
├── reverb       (Laravel Reverb — WebSockets, reemplaza Supabase Realtime)
├── queue        (php artisan queue:work, mismo código que app, réplica del contenedor)
├── scheduler    (php artisan schedule:work — reemplaza el cron de Vercel)
└── minio        (opcional: S3-compatible para storage de medios/avatares)
```

Vite compila los assets de Inertia+React igual que hoy compila Next. El proyecto usa
**Tailwind 4**, que no tiene `tailwind.config.js`: la configuración es CSS-first
(`@import "tailwindcss"` + `@theme`) y hoy vive en `src/app/globals.css` — ese archivo
se lleva tal cual a `resources/css/app.css` en Laravel. Lo único que cambia es el
plugin de build: hoy Next usa `@tailwindcss/postcss`; con Laravel+Vite se usa el
plugin oficial `@tailwindcss/vite` en `vite.config.ts`. Mismos componentes
shadcn/tremor, sin SSR de Next (Inertia hace SSR opcional vía
`inertiajs/inertia-laravel` + Node side-car si se necesita, pero para un CRM interno
no es obligatorio empezar con eso).

## Mapeo pieza por pieza

### 1. Auth y multi-tenant (`src/proxy.ts`, `src/lib/auth/**`)
Supabase Auth → **Laravel Sanctum (sesión SPA)**. El modelo de "accounts" con
miembros/roles/invitaciones (`src/lib/auth/account.ts`, `roles.ts`, `invitations.ts`)
se traduce a tablas Eloquent normales (`accounts`, `account_user`, `invitations`) con
un `Gate`/Policy por rol, ya no RLS de Postgres. El middleware de `proxy.ts` pasa a ser
un Laravel Middleware (`EnsureAccountAccess`) + `Inertia::share` para el usuario/cuenta
actual.

### 2. Base de datos (36 migraciones SQL)
Se reescriben como Laravel migrations (`php artisan make:migration`), una por una,
preservando el orden y los nombres de tabla/columna para minimizar riesgo. Los RPCs de
Postgres (`account_member_rpcs`, `invitation_rpcs`, contadores incrementales de
broadcasts/flows/automations) se convierten en:
- Triggers SQL puros si son invariantes de datos (contadores) → se quedan en SQL, están
  bien ahí, Postgres es el mismo motor.
- Lógica de autorización (lo que hacían para esquivar RLS) → pasa a Eloquent
  scopes/policies en PHP.

RLS se **elimina**: en el modelo Laravel, el aislamiento multi-tenant se hace con un
global scope (`AccountScope`) aplicado a cada modelo, no con políticas de Postgres.
Es una app monolítica confiando en su propio código, no clientes anónimos hablando
directo con la DB (ese era el motivo de RLS en Supabase).

### 3. Realtime (`use-realtime.ts`, `use-presence.ts`, `use-unread-notifications.ts`)
Supabase Realtime (`postgres_changes` sobre `messages`/`conversations`) → **Laravel
Reverb** + `laravel-echo` en el frontend. Los eventos ya no son "cambios de fila
crudos"; se emiten explícitamente desde donde hoy se hace el `INSERT`/`UPDATE` (ej.
`MessageReceived`, `ConversationUpdated`) vía `event()->broadcast()`. Esto es más
código que hoy (Supabase lo hacía gratis por trigger de WAL) pero es el patrón estándar
Laravel y evita re-implementar logical replication.

### 4. Storage (avatares, media de chat, `src/lib/storage/upload-media.ts`)
Supabase Storage → Laravel `Storage` facade con driver `s3` apuntando a **MinIO** en
docker-compose (compatible con el mismo SDK S3, cero cambios si el día de mañana se
quiere mover a S3/R2 real). URLs firmadas via `Storage::temporaryUrl()`.

### 5. Motor de Automations y Flows (`src/lib/automations/engine.ts`,
   `src/lib/flows/engine.ts`, ~1900 líneas combinadas)
Es lógica de dominio pura (evaluar condiciones, avanzar pasos, encolar el siguiente
step) — se porta 1:1 a clases PHP (`App\Services\Automations\Engine`,
`App\Services\Flows\Engine`). El cron de Vercel (`/api/automations/cron`,
`/api/flows/cron`, protegidos por `x-cron-secret`) se reemplaza por
`php artisan schedule:work` + un `Job` en la cola de Redis — ya no hace falta el
shared-secret HTTP porque el scheduler corre dentro del propio contenedor, no como
webhook externo.

### 6. WhatsApp / Meta API (`src/lib/whatsapp/meta-api.ts`, envío, templates, webhook)
Se porta a un `MetaWhatsappClient` en PHP usando `Illuminate\Http\Client` (Guzzle por
debajo). El webhook entrante (`/api/whatsapp/webhook`) sigue siendo un endpoint HTTP
público con verificación HMAC-SHA256 (`META_APP_SECRET`) — misma lógica, distinto
lenguaje. El cifrado de tokens (`src/lib/whatsapp/encryption.ts`, AES-256-GCM) se
reemplaza por el `Crypt` facade de Laravel (mismo algoritmo, misma garantía).

### 7. IA (BYOK OpenAI/Anthropic + embeddings + pgvector)
Se mantiene el diseño *bring-your-own-key*: clave cifrada por cuenta en la tabla
`ai_config` (ya existe, migración 029/030), llamadas HTTP directas a los providers
desde un `AiService` en PHP. `pgvector` sigue siendo la misma extensión de Postgres —
solo cambia quién la corre (contenedor propio vs. Supabase managed).

### 8. API pública v1 y webhooks salientes (`src/app/api/v1/**`, `src/lib/webhooks/**`)
API keys con scopes (`src/lib/api-keys/**`) → Laravel `Sanctum` personal access
tokens o un guard custom si se necesita mantener el mismo formato de key ya emitido a
usuarios. Webhooks salientes con firma HMAC y guard SSRF (`src/lib/webhooks/ssrf.ts`)
se portan igual — es lógica de validación de URL, no depende de Supabase.

### 9. i18n (`next-intl`, `messages/es-CO.json`, `en.json`)
Laravel trae i18n nativo (`lang/es/*.php`, `lang/en/*.php`); los JSON de mensajes se
convierten a arrays PHP con un script de una sola vez (mismo árbol de claves).

## Fases (orden recomendado, cada fase entregable y verificable sola)

1. **Andamiaje docker-compose** — Laravel 13 fresh install, Postgres+pgvector, Redis,
   Reverb, Vite, todo levantando con `docker compose up`, sin lógica de negocio aún.
2. **Esquema de datos** — portar las 36 migraciones SQL a Laravel migrations +
   seeders mínimos. Verificar con un diff de esquema contra la DB de Supabase actual.
3. **Auth + multi-tenant** — Sanctum, accounts, roles, invitaciones, middleware de
   acceso. Login/signup/forgot-password funcionando en Inertia+React.
4. **Contactos, Pipelines, Deals** (CRUD más simple, sin WhatsApp ni realtime) —
   primer módulo de negocio de punta a punta, valida el patrón Inertia end-to-end.
5. **WhatsApp: envío, webhook, templates** — el corazón del CRM.
6. **Inbox + Realtime (Reverb)** — depende de 5.
7. **Automations + Flows** — motor de reglas, el más grande, dejar para cuando el
   resto del pipeline de mensajes ya funcione.
8. **Broadcasts, AI assistant, API pública v1, webhooks salientes** — features
   añadidas, cada una independiente entre sí, se pueden paralelizar.
9. **Cutover**: export/import de datos reales de Supabase → Postgres propio (mismo
   motor, `pg_dump`/`pg_restore` funciona directo), apagar Supabase/Vercel.

## Qué NO cambia

- El esquema de datos en sí (nombres de tabla/columna) se conserva — reduce riesgo y
  hace el `pg_dump`/`pg_restore` de cutover trivial.
- El diseño BYOK de IA, el cifrado AES-256-GCM, el guard SSRF de webhooks, el modelo de
  API keys con scopes: son decisiones de producto correctas, se portan tal cual.
- Todos los componentes React de UI (shadcn, tremor, xyflow, dnd-kit, recharts):
  Inertia los sirve igual que Next, sin reescritura.

## Riesgos principales

- **Realtime es el mayor riesgo técnico**: Supabase daba eventos "gratis" por WAL;
  en Laravel hay que emitir cada evento a mano desde cada punto de escritura. Fácil
  de olvidar uno y romper un caso de uso silenciosamente. Mitigación: checklist de
  cada `INSERT`/`UPDATE` sobre `messages`/`conversations` que hoy dispara realtime,
  verificar que su equivalente Laravel tenga el `broadcast()` correspondiente.
- **RLS → Eloquent scopes**: si un scope de cuenta se olvida en un modelo nuevo, hay
  fuga de datos entre tenants (antes lo bloqueaba la DB a nivel de fila). Mitigación:
  trait `BelongsToAccount` aplicado por defecto a todo modelo de dominio + tests de
  aislamiento multi-tenant.
- **Motor de Flows (1117 líneas)** es el módulo más grande y con más edge cases
  (fallbacks, layout de nodos, validación) — portarlo de último minimiza el tiempo
  bajo dos motores en paralelo.

## Siguiente paso concreto

Con este plan aprobado, el primer entregable es la Fase 1 (andamiaje docker-compose)
en un directorio nuevo (ej. `laravel/`) dentro de este mismo repo o uno separado —
conviene decidir eso antes de tocar código.
