# 0011 — Inbox en vivo por canal de Account y envío sin visita Inertia

- **Estado:** Aceptado
- **Fecha:** 2026-08-17
- **Contexto:** wacrm — Inbox

## Contexto

El Inbox pinta Conversations y Messages que ya están en Postgres, pero la UI no se entera sola. Un inbound llega por el webhook (ADR 0008) y solo aparece en la siguiente visita a `GET /inbox`. El envío usa `router.post` y redirige a esa misma página: no hay burbuja `sending`, y un redirect se llevaría por delante cualquier estado local.

Reverb, Echo y `laravel-echo` ya están en el stack; no hay `app/Events` ni listeners. En este repo **Broadcast** es el dominio Difusiones, no un WebSocket.

## Decisión

La bandeja y el hilo abierto se actualizan por **Laravel Echo** sobre **Reverb**, no por poll de Inertia. El contrato de producto es: preview, hora, Conversation nueva y, si el hilo está activo, la burbuja. Un outbound de otro miembro del Account usa el mismo camino y no incrementa el **Unread Count**.

Hay **un canal privado por Account**. La UI filtra por `conversation_id`. Así una Conversation que acaba de nacer (primer inbound a esa Connection) llega sin que el cliente estuviera suscrito a ese hilo.

El POST de envío **no es una visita Inertia**. Responde el Message persistido o un error de validación. El cliente muestra una burbuja local `sending` (Spinner de shadcn en el Button y en la burbuja), canjea el id temporal al 2xx y deja `failed` + reintento si Graph rechaza o hay timeout. El servidor sigue llamando a Graph **antes** de persistir. Un reintento es otro envío; si el timeout llegó después de un 200 de Graph, el Contact puede recibir dos WhatsApp.

El **Unread Count** es del Account (glosario). Cada inbound nuevo suma uno. Un Member o superior con el hilo activo —clic o Conversation por defecto al cargar Inbox— persiste cero, también para el mensaje que acaba de entrar. Un Viewer no apaga el badge.

Quedan fuera de esta decisión: poll como contrato, un canal por Conversation, presence, persistir `sending` antes de Graph, ticks Meta en vivo, unread por User, y vender los chips CRM (`open` / `pending` / `closed`) como estado de Meta.

## Alternativas consideradas

### `usePoll` de Inertia (como Settings WhatsApp)

Se rechaza como contrato. Cubre “antes o después recarga”, no “me acaban de escribir”. Settings espera un cambio de readiness; Inbox espera el Message.

### Un canal privado por Conversation

Se rechaza porque Q3 exige que una Conversation nueva aparezca en la lista. Eso no puede suscribirse después de existir. N canales por bandeja tampoco escala con el Account.

### Presence para no incrementar unread si alguien mira el hilo

Se aplaza. El invariante de este corte es: el servidor siempre suma; el cliente con hilo activo persiste el cero. Presence es otro sistema.

### Redirect Inertia o `preserveState` + `only`

Se rechaza. Una visita pelea con la burbuja local y con el evento Echo del mismo envío. El canje del id temporal necesita un cuerpo, no una rehidratación de página.

### Persistir `sending` antes de Graph

Se aplaza. El dolor actual es latencia percibida, no sobrevivir un F5 a los 200 ms. Endurecer el seam de `StoreInboxMessageService` es un corte distinto.

## Consecuencias

- Autorizar el canal por membresía del Account, no por Conversation.
- Emitir al persistir inbound (webhook) y al persistir outbound (send); el emisor reconcilia por la respuesta HTTP, los demás por Echo.
- No llamar “broadcast” al evento de Inbox en copy de producto: choca con Difusiones.
- Settings puede seguir usando poll para readiness; Inbox no copia ese patrón.
