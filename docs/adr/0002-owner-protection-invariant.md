# 0002 — Invariante Owner Protection (último Owner no degradable)

- **Estado:** Aceptado
- **Fecha:** 2026-07-18
- **Contexto:** wacrm (subproyecto Laravel 13 + Inertia + Fortify + Reverb)

## Contexto

Cada `Account` debe tener **al menos un Owner** en todo momento. Owner es el único rol con permisos de billing y eliminación de cuenta; sin él, el Account queda ingobernable.

Las operaciones de gestión de miembros — cambiar rol, remover miembro, autoeliminarse — pueden dejar el Account sin Owner si se aplican sin coordinación.

## Decisión

Aplicamos la regla **"último Owner no degradable ni autoeliminable"** en la **capa de aplicación** (Actions), no en SQL.

### Alcance de la regla

1. **Cambio de rol**: si el miembro es Owner y sería el único Owner restante, la Action devuelve `MemberActionStatus::LastOwnerBlocked` sin ejecutar el `UPDATE`.
2. **Remoción**: si el miembro es Owner y sería el único Owner restante, la Action devuelve `LastOwnerBlocked` sin ejecutar el `DELETE`.
3. **Auto-eliminación** (caso futuro, ej. "abandonar este Account"): mismo bloqueo.
4. **Promover a otro Owner primero** es la única vía legal para "soltar" el rol de Owner del último Owner.

### Dónde vive

- `App\Domain\Accounts\Actions\ChangeMemberRole` consulta `count(AccountUser WHERE role=Owner AND account_id=?)` antes del update.
- `App\Domain\Accounts\Actions\RemoveMember` igual antes del delete.
- Si el conteo post-acción sería 0 → bloquea.

> **Actualización 2026-08-06 (issue #83).** La regla ya no vive en las Actions: `App\Domain\Accounts\Support\MembershipRules` la decide para los tres flujos de membresía y devuelve `MemberActionStatus`. Las Actions leen los hechos —incluido el conteo de Owners— y ejecutan la escritura dentro de una transacción que abre bloqueando la fila del Account, porque bloquear el pivot del objetivo no impedía que dos degradaciones simultáneas de Owners distintos leyeran ambas un conteo de 2. La autorización se evalúa antes que este invariante: quien no puede gestionar miembros recibe `Forbidden` y no llega a saber cuántos Owners quedan.

### Mensaje de error

`LastOwnerBlocked` se mapea a HTTP 422 con mensaje traducible: "Promovés a otro Owner antes de degradar tu rol." (i18n siguiendo ADR-pendiente #24).

> **Actualización 2026-08-06 (issue #95).** El 422 nunca se implementó: es un 302 con `withErrors(['last_owner_blocked' => ...])`, que es lo que Inertia necesita. La copia vigente es "Promueve a otro Owner antes de degradar el rol." y vive en `MemberActionResponder`. La tabla de códigos HTTP de los tres endpoints está en ADR 0005.

## Consideradas

- **Trigger SQL**: rechazado por costo. Garantía más fuerte pero debugging más opaco, y la regla es trivialmente enforceable en PHP para este volumen.
- **Constraint CHECK**: imposible directamente (cuenta agregada sobre filas de la misma tabla requiere subquery, que Postgres acepta pero Laravel/Eloquent no modela idiomáticamente).
- **Solo bloque de UI**: rechazado. Un script de seed o un request directo lo sortearía.

## Consecuencias

- Tests obligatorios: "Owner único intenta degradarse → bloqueado"; "Owner degrada a Admin habiendo otro Owner → procede"; "Admin intenta promover a Owner → procede".
- Si más adelante se necesita fortaleza extra (ej. script de seed que toque la tabla directo), revisamos con un ADR nuevo. No lo añadimos ahora por YAGNI.

## Reversibilidad

Alta. La regla vive en 2 métodos de Action; cambiar a "trigger SQL" es un ADR local sin impacto en la API.