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

## Infraestructura de tenant

- **CurrentAccount** — value object readonly con `id` (UUID) y `role`, bindeado por request.
- **AccountScope** — global scope de Eloquent que filtra `account_id`. Sin tenant bindeado, falla cerrado (`WHERE 1=0`).
- **BelongsToAccount** — trait que aplica `AccountScope` y autopopula `account_id` al crear.
- **EnsureCurrentAccount** — middleware que resuelve el account actual desde sesión, redirige al switcher si no hay, y 403 si el User ya no es miembro.

## Patrón arquitectónico

ADR 0001: **Action / Domain / Responder** para features nuevos. Las Actions viven en `app/Domain/<Contexto>/Actions/`, los Responders en `app/Domain/<Contexto>/Responders/`, y los Results son value objects readonly con enum de estado. Fortify (`app/Actions/Fortify/*`) no se migra.

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

**Member Removal scope**:
Remover a un miembro de un Team Account elimina **solo esa membresía**. El Personal Account del User removido queda intacto. Coherente con el modelo multi-account.

**MemberActionStatus** (enum de resultado de las Actions de Accounts):
`Success` | `LastOwnerBlocked` | `NotMember` | `Forbidden`.
