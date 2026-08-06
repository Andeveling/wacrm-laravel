# CONTEXT.md — wacrm

Glosario de lenguaje ubicuo del dominio. Mantenido por `/grill-with-docs` y referenciado por todos los skills de ingeniería.

## Entidades

### Account
El **tenant**. Todo dato operativo (contactos, negocios, conversaciones, API keys) pertenece a un Account. Dos tipos:
- **Personal** — auto-creado al registrarse, uno por User, nombre "Personal".
- **Team** — compartido entre varios Users, se ingresa por invitación.

### AccountUser (pivot)
La **membresía** que une User ↔ Account. Carga el **AccountRole** y la fecha de ingreso (`joined_at`). Un User tiene exactamente un rol por Account.

### Invitation
Invitación para que un User existente se una a un Team Account. Tiene token hasheado (SHA-256, el plaintext nunca se persiste), rol asignado, expiración, y puede estar **revocada** o **aceptada**. El método `previewByTokenHash()` es el punto de entrada anónimo para la página `/join/{token}`.

### ApiKey
Token Bearer para acceso programático. Pertenece a un Account y carga un conjunto de **ApiScope**. Es inmutable tras crearse (no tiene `updated_at`). Implementa `AuthenticatableContract`: la key misma es la identidad del guard.

### Contact
Persona con la que el Account conversa por WhatsApp. Su **proyección pública** — `id`, `phone`, `name`, `email`, `company`, `avatar_url`, `created_at`, `updated_at` y sus `tags` — está definida una sola vez y es idéntica en todos los transportes: pantalla web, tools MCP y (cuando exista) la API REST. Agregar un campo al Contact público es editar ese único lugar. El detalle que devuelve MCP es esa misma proyección más `notes` y `conversations`.

## Roles (AccountRole)

Jerarquía ordinal plana de lo que un User puede hacer dentro de un Account:

| Rol      | Rango | Qué puede hacer                                                 |
| -------- | ----- | --------------------------------------------------------------- |
| Owner    | 4     | Control total: billing, eliminar account.                       |
| Admin    | 3     | Gestionar miembros (invitar, remover, cambiar roles), settings. |
| Member   | 2     | Escribir datos operativos (contactos, negocios, conversaciones).|
| Viewer   | 1     | Solo lectura de datos operativos.                               |

`AccountPolicy` es la fuente única de verdad de capacidades por rol.

## Estados de invitación (InvitationStatus)

`Valid` | `Used` | `Expired` | `Invalid` (desconocida o revocada).

## Scopes de API (ApiScope)

`messages:send` | `messages:read` | `contacts:read` | `contacts:write` | `conversations:read` | `broadcasts:send` | `webhooks:manage`.

Un `null` scope otorga solo autenticación (permite `GET /api/v1/me`).

## MCP (Model Context Protocol)

### WacrmServer
Servidor MCP único que expone la capa de servicio del CRM a agentes AI. Registrado como ruta web (`/mcp/wacrm`) y como servidor local (`wacrm`). Contiene 18 tools organizadas en 9 dominios: Inbox, Contacts, Pipelines, Broadcasts, Automations, Flows, AI Assistant, Members, Settings.

### AuthenticateMcp (middleware `auth.mcp`)
Middleware que autentica peticiones MCP reutilizando el guard `api_key` existente. Resuelve el `ApiKey` desde el header `Authorization: Bearer`, bindea el `account_id` en `AccountScope` para tenant scoping, y stash el `ApiKey` resuelto en `$request->attributes['api_key']`. A diferencia de `AuthenticateApiKey`, no escribe audit trail — los tools MCP son solo lectura.

### MCP Tool
Cada tool es una clase que extiende `Laravel\Mcp\Server\Tool` y declara un schema de entrada JSON y un método `handle(Request): Response`. Las tools heredan el tenant scoping vía `BelongsToAccount` — no necesitan filtrar por `account_id` manualmente porque el middleware ya bindeó el scope.

## Infraestructura de tenant

- **CurrentAccount** — value object readonly con `id` (UUID) y `role`, bindeado por request.
- **AccountScope** — global scope de Eloquent que filtra `account_id`. Sin tenant bindeado, falla cerrado (`WHERE 1=0`).
- **BelongsToAccount** — trait que aplica `AccountScope` y autopopula `account_id` al crear.
- **EnsureCurrentAccount** — middleware que resuelve el account actual desde sesión, redirige al switcher si no hay, y 403 si el User ya no es miembro.

## Patrón arquitectónico

ADR 0001: **Action / Domain / Responder**. Las Actions viven en `app/Domain/<Contexto>/Actions/`, los Responders en `app/Domain/<Contexto>/Responders/`, y los Results son value objects readonly con enum de estado. Fortify (`app/Actions/Fortify/*`) no se migra.

`app/Http/Controllers/` ya no existe: los contextos `Accounts`, `Contacts`, `Dashboard`, `Invitations`, `Meta` y `Settings` viven completos bajo `app/Domain/`. `app/Http/` conserva solo lo que es realmente HTTP: `Middleware/` y `Requests/` (los FormRequest siguen siendo el input boundary).

Los endpoints que solo renderizan una página o aplican una regla única no llevan Result ni Responder — la Action devuelve la respuesta directamente (regla 4 del ADR 0001). Result + Responder se reservan para flujos con varios desenlaces legales: los tres de membresía (`MemberActionResult`), `RedeemInvitation` (302 / 401 / 409 / 422) y `ReceiveMetaWebhook` (200 / 400 / 401 / 413 / 503).

ADR 0005: **el Responder se comparte cuando el Result se comparte**. Los tres flujos de membresía devuelven el mismo `MemberActionResult`, así que los atiende un único `MemberActionResponder` que recibe el resultado y el destino. Dos flujos con Results distintos siguen teniendo Responders distintos.

## Organización de las pruebas

ADR 0004: cada comportamiento se prueba una sola vez, en el seam público más alto que lo observe. Las cuatro suites de `phpunit.xml` son ese contrato:

| Suite | Cubre | Base de datos |
| --- | --- | --- |
| `Domain` | reglas PHP puras de un contexto | no |
| `Unit` | reglas PHP puras fuera de `app/Domain` | no |
| `Feature` | seams HTTP, Inertia, API y MCP | sí |
| `Browser` | interacciones críticas de React | sí |

`tests/Feature/` y `tests/Domain/` espejan los contextos de `app/Domain/` — `Accounts`, `Contacts`, `Dashboard`, `Invitations`, `Meta`, `Settings` — más los seams transversales `Api`, `Auth`, `Concerns` y `Jobs`, que no pertenecen a un solo contexto. Los helpers compartidos viven en `tests/Concerns/` y `tests/Fixtures/`, fuera de las suites, porque Pest solo recoge `*Test.php`.

Lo hace cumplir `tools/lint/test-layout.php` en el `pre-commit`; las reglas están en `AGENTS.md`.

## Términos clave

- **Account** — tenant. Todo está escopeado a uno.
- **Personal Account** — cuenta privada auto-creada por User.
- **Team Account** — cuenta compartida, acceso por invitación.
- **Rol** — rango del User en un Account (Owner > Admin > Member > Viewer).
- **Invitación** — token temporal y revocable para unirse a un Team.
- **API Key** — token Bearer con scopes para acceso programático.
- **Current Account** — el account en el que el User está "actuando" en este request.
- **BelongsToAccount** — marcador de que un modelo está escopeado por tenant.
- **Datos operativos** — contactos, negocios, conversaciones (CRM).

## Reglas de membresía

**Owner Protection**:
Un Account debe tener **al menos un Owner** en todo momento. La última membresía con rol Owner no puede degradarse ni ser removida. Para soltar el rol de Owner, hay que promover a otro miembro a Owner primero. Regla enforced en la capa de aplicación (Actions de Accounts), no en SQL — ver ADR 0002.

**Precedencia**:
La autorización decide antes que Owner Protection. Un actor que no puede gestionar miembros recibe `Forbidden` sin importar el estado del Account: no llega a saber si el objetivo es miembro ni cuántos Owners quedan.

**Asimetría remover / degradar**:
Un Admin puede **remover** a un Owner mientras quede otro Owner, pero nunca puede **degradarlo**. Remover deja intacta la jerarquía de roles; degradar reescribe el tramo en el que el propio Admin está parado. Solo un Owner muta filas Owner o promueve a Owner.

**Member Removal scope**:
Remover a un miembro de un Team Account elimina **solo esa membresía**. El Personal Account del User removido queda intacto. Coherente con el modelo multi-account.

**MemberActionStatus** (enum de resultado de las Actions de Accounts):
`Success` | `LastOwnerBlocked` | `NotMember` | `Forbidden`.
