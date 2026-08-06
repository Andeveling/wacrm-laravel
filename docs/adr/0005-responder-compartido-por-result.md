# 0005 — Un Responder por Result compartido, no por flujo

- **Estado:** Aceptado
- **Fecha:** 2026-08-06
- **Contexto:** wacrm — contexto acotado `Accounts`

## Contexto

Los tres flujos de membresía — invitar, cambiar rol, remover — devuelven el mismo `MemberActionResult`, con el mismo enum `MemberActionStatus` de cuatro casos. Cada uno tenía su propio Responder, y el mapeo estaba escrito tres veces en 182 líneas.

Las tres copias ya habían divergido:

- `InviteMemberResponder` mapeaba `NotMember` a **403** mientras los otros dos respondían **404**.
- `ChangeMemberRoleResponder` mostraba el mensaje de `LastOwnerBlocked` en español y `RemoveMemberResponder` en inglés, porque uno leía el default del Result y el otro su propio fallback.

Ninguna de las dos diferencias tenía razón de dominio: son el residuo de haber escrito el mismo `match` tres veces en momentos distintos.

Lo que sí difiere entre flujos es presentación: a dónde redirige el éxito, qué clave de sesión lleva y si muestra toast.

## Decisión

**Un Responder por Result compartido.** `MemberActionResponder` es el único Responder del contexto `Accounts` para membresías. Recibe el Result y el destino:

```php
($this->responder)($result, flash: 'role_changed', toast: 'Rol actualizado.', route: 'accounts.members.index');
```

- `$flash` — clave de sesión que la página lee.
- `$toast` — copia del toast Inertia, o `null` si el flujo no muestra ninguno.
- `$route` — ruta nombrada de destino; `null` redirige con `back()`.

El mapeo de estados queda fijo y compartido. Esto no reemplaza la regla de ADR 0001, la precisa: **el Responder se comparte cuando el Result se comparte**. Dos flujos con Results distintos siguen teniendo Responders distintos.

### Incoherencia 403 / 404

Resuelta hacia **404**. `NotMember` significa "el objetivo no es miembro de este Account", que es una condición del recurso, no del actor — la autorización ya se resolvió antes (ver ADR 0002 y `MembershipRules`). El 403 de `InviteMemberResponder` era además inalcanzable: invitar no consulta la membresía del objetivo, así que ese flujo no puede producir `NotMember`.

### Códigos HTTP de los tres endpoints

| Estado | `POST .../members` (invitar) | `PATCH .../members/{member}` (cambiar rol) | `DELETE .../members/{member}` (remover) |
| --- | --- | --- | --- |
| `Success` | 302 `back()` + flash `invited` | 302 a `accounts.members.index` + flash `role_changed` | 302 `back()` + flash `member_removed` |
| `LastOwnerBlocked` | inalcanzable | 302 a `accounts.members.index` + error `last_owner_blocked` | 302 `back()` + error `last_owner_blocked` |
| `Forbidden` | 403 | 403 | 403 |
| `NotMember` | inalcanzable | 404 | 404 |

Ninguno cambia respecto de lo que ya hacía el código. ADR 0002 afirma que `LastOwnerBlocked` se mapea a 422; nunca fue cierto en la implementación y esta tabla es la que manda: es un 302 con `withErrors`, que es lo que Inertia necesita para repoblar el formulario.

## Consideradas

- **Mantener un Responder por flujo.** Rechazado: lo único genuinamente duplicado eran `abort(403)` y `abort(404)`, pero la duplicación del `match` fue exactamente el mecanismo por el que las respuestas divergieron. La convención que produce la deriva es la que hay que cambiar.
- **Mover la presentación al Result.** Rechazado: el Result es un value object de dominio. Meterle clave de flash y ruta lo convierte en un DTO de transporte y rompe la regla 5 del lint de capas en espíritu, aunque no en la letra.
- **Un Responder por endpoint que herede de una base común.** Rechazado: tres subclases de una línea son la misma duplicación con más archivos.

## Consecuencias

- `MemberActionResult` queda en `status` + `account`. `member` y `message` no los leía ningún Responder: `message` porque la copia ahora vive en el Responder, y `member` porque nadie re-renderizaba la fila. Con `member` se va también la relectura del pivot que hacía `ChangeMemberRole` después de cada `UPDATE`.
- Se eliminan los constructores nombrados del Result y `MemberActionStatus::label()`, que solo tenía consumidor en su propia prueba.
- La regla 4 del lint de capas (`tools/lint/adr-layers.php`) pasa sin ajustes: `MemberActionResponder` es `final`, termina en `Responder` y no importa modelos ni el query builder.
- La copia en español vive en un solo lugar. Se corrigió el voseo de ADR 0002 ("Promovés") a "Promueve a otro Owner antes de degradar el rol.".

## Reversibilidad

Alta. Volver a un Responder por flujo es copiar el `match` tres veces; el contrato HTTP de los endpoints no depende de esta decisión.
