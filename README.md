# wacrm — CRM de WhatsApp, IA-first, edición LATAM

Un CRM de WhatsApp que corre entero en **un VPS de 20 dólares**, con un servidor
**MCP nativo** para que cualquier agente de IA — Claude, Cursor, el que uses —
opere el sistema como un usuario más.

Sin Vercel. Sin Supabase. Sin plan gratuito que caduca. `docker compose up` y es
tuyo.

---

## Por qué existe

Este proyecto es una reconstrucción de [wacrm](https://github.com/ArnasDon/wacrm),
un CRM de WhatsApp en Next.js + Supabase de Arnas Donauskas.

**El original no tiene nada malo.** Es un buen producto y su stack es una elección
sensata: Vercel y Supabase resuelven despliegue, auth y realtime casi sin
configuración. Si eso encaja con tu caso, úsalo — está en MIT y es excelente.

Lo reconstruí porque quería dos cosas distintas:

**Soberanía de despliegue.** Un CRM guarda las conversaciones de tus clientes.
Para muchos negocios en LATAM, eso vive mejor en una máquina que controlan, en
la jurisdicción que eligen, con un costo que no escala con el éxito. Todo aquí
corre en `compose.yaml`: Postgres con pgvector, Redis, WebSockets, colas y
scheduler. Un droplet, un `git pull`, listo. Cero servicios gestionados en la
ruta crítica.

**MCP como interfaz de primera clase, no como plugin.** El mundo se está
moviendo hacia "todo en un chat". Un CRM cuyo único acceso programático es una
API REST obliga a cada usuario a construir su propio pegamento. Aquí el servidor
MCP es parte del núcleo, autenticado con el mismo sistema de API keys que el
resto de la plataforma, con el mismo aislamiento por tenant.

La meta es cero fricción entre lo que el usuario quiere y lo que el sistema
hace: que puedas decirle a tu agente *"busca los contactos que no responden hace
dos semanas y arma una difusión"* y que funcione, sin escribir integraciones.

---

## MCP: la parte interesante

18 herramientas expuestas sobre el dominio, agrupadas por área:

| Área | Herramientas |
| --- | --- |
| Contactos | `ListContacts` · `GetContact` · `SearchContacts` |
| Bandeja | `ListConversations` · `GetConversation` · `SearchMessages` |
| Embudos | `ListPipelines` · `ListDeals` · `GetDeal` |
| Difusiones | `ListBroadcasts` · `GetBroadcast` · `ListTemplates` |
| IA | `GetAiConfig` · `GetAiUsage` |
| Otros | `ListMembers` · `ListAutomations` · `ListFlows` · `GetAccountSettings` |

Dos transportes, según cómo trabajes:

```php
Mcp::web('/mcp/wacrm', WacrmServer::class);   // HTTP/SSE, para agentes remotos
Mcp::local('wacrm', WacrmServer::class);      // stdio, para Claude Code y afines
```

**La decisión de la que estoy más conforme** está en
[ADR 0003](docs/adr/0003-mcp-api-key-auth.md). Laravel MCP documenta dos caminos
de autenticación: OAuth 2.1 con Passport, o Sanctum. El proyecto ya tenía un
sistema de API keys propio y probado — tokens `wacrm_{live|test}_<64 hex>`,
hash SHA-256 con el plaintext nunca persistido, scopes granulares, audit trail y
rate limiting por key en vez de por IP.

Instalar Passport habría significado un segundo sistema de identidad conviviendo
con el primero. En su lugar, un middleware `auth.mcp` reutiliza el guard
existente y bindea el `account_id` en `AccountScope`. Resultado: **los 18 tools
heredan el aislamiento por tenant sin una línea de código de tenancy en ninguno
de ellos.**

---

## Estado actual

Honestamente: **la plataforma está construida, los módulos de producto no.**

Lo que funciona hoy, con tests:

- **Auth completo** sobre Fortify — registro, login, 2FA/TOTP, passkeys (WebAuthn), reset de contraseña
- **Multi-tenant fail-closed** — `BelongsToAccount` + `AccountScope`; sin cuenta ligada, la consulta falla en vez de filtrar de más. Cubre HTTP, consola y jobs en cola (`TenantAware`)
- **Roles y permisos** — jerarquía ordinal Owner/Admin/Member/Viewer, con el invariante de protección del owner en [ADR 0002](docs/adr/0002-owner-protection-invariant.md)
- **Invitaciones** — token hasheado, expiración, revocación, página pública de canje
- **API keys** — scopes, audit trail, rate limit por key
- **Servidor MCP** — 18 tools, dos transportes
- **Ingesta del webhook de Meta** — verificación de firma HMAC y bandeja durable que preserva el body byte-exacto para poder reprocesar

Lo que **no** está todavía:

- Los módulos de producto (Contactos, Bandeja, Embudos, Difusiones, Automatizaciones, Flujos, Agentes) tienen la interfaz portada pero **no tienen backend**. Se ven; no operan.
- La capa de IA tiene el esquema y el SDK listos (pgvector, `ai_configs`, chunks de conocimiento, log de uso) pero la lógica de RAG y agentes está sin escribir.
- Los tests de navegador están escritos pero no corren dentro de Sail — falta Playwright en la imagen.

El esquema de datos sí está completo: 28 migraciones, 39 modelos, portado desde
el Supabase original con cada desviación documentada.

---

## Stack

| Capa | Elección |
| --- | --- |
| Backend | Laravel 13 · PHP 8.4 |
| Frontend | Inertia v3 · React 19 · Tailwind 4 · TypeScript |
| Datos | PostgreSQL 17 + pgvector 0.8 |
| Realtime | Reverb (WebSockets propios, sin Pusher) |
| Colas y caché | Redis |
| IA | Laravel AI SDK · Laravel MCP |
| Entorno | Laravel Sail — un `compose.yaml`, seis servicios |

pgvector va incluido a propósito: el RAG corre en la misma Postgres que el resto
del dominio. Sin base vectorial aparte, sin otro servicio que pagar.

---

## Arranque

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
pnpm install && pnpm dev
```

O directamente `./init.sh`, que hace todo lo anterior y espera a que Postgres
esté sano.

- App — <http://localhost:8000>
- WebSockets — `localhost:8080`
- Postgres — `localhost:5433`

Usuarios de demo (contraseña `password`): `admin@demo.test`, `agent@demo.test`,
`viewer@demo.test`.

---

## Calidad

El estándar está **aplicado por herramientas, no pedido por convención**.
Lefthook corre en cada commit, sobre los archivos en stage y en paralelo:

| Verificación | Comportamiento |
| --- | --- |
| Pint · Biome | corrigen y re-agregan al stage |
| PHPStan (nivel 7) · TypeScript | bloquean |
| `no-inline-fqcn` · idioma de UI | bloquean |
| commitlint | Conventional Commits en el `commit-msg` |

Los dos linters propios valen una nota: uno prohíbe FQCN inline para forzar
imports con `use`; el otro rechaza voseo rioplatense en textos de interfaz,
porque el producto apunta a español neutro para todo LATAM.

50 archivos de test sobre Pest 4, y las decisiones de arquitectura viven en
[`docs/adr/`](docs/adr/) en lugar de en la cabeza de alguien.

---

## Créditos y licencia

Reconstruido a partir de [wacrm](https://github.com/ArnasDon/wacrm) de
**Arnas Donauskas**, MIT. El código original en Next.js queda archivado en
[`original-wacrm/`](original-wacrm/) como referencia de comportamiento.

Este proyecto también es MIT. Desplegalo donde quieras; ese es el punto.
