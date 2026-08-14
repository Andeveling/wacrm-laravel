# 0008 — Webhook global y conexiones WhatsApp multinúmero

- **Estado:** Aceptado
- **Fecha:** 2026-08-14

## Contexto

Wacrm es una aplicación open source multi-tenant que cada operador instala con su propia Meta App. El modelo heredado permite una sola fila de configuración WhatsApp por Account y el ingreso actual conserva entregas firmadas, pero todavía no resuelve eventos hacia tenants ni representa correctamente que un Account puede operar varios números, incluso de varios WABA.

Meta configura el callback por aplicación y cada evento de mensajes identifica el número receptor mediante `metadata.phone_number_id`. Meta también puede reintentar entregas durante siete días y producir duplicados, por lo que confirmar una entrega no puede depender de completar sincrónicamente todos sus efectos en el CRM.

## Decisión

Cada instalación tendrá una Meta App y un endpoint global de webhooks. El App Secret y verify token pertenecen a la instalación; HMAC con `X-Hub-Signature-256` es obligatorio. Cada Account mantiene su propia WhatsApp Integration y puede conectar varios WhatsApp Phone Numbers de uno o varios WABA, pero un WABA pertenece a un solo Account dentro de la instalación. La suscripción a webhooks se representa por WABA y las conexiones se identifican globalmente por `phone_number_id`.

Una Webhook Delivery es un sobre global firmado, no una fila tenant-aware. El ingreso valida firma y tamaño real de hasta 3 MB, conserva el cuerpo exacto y responde `200` apenas la entrega queda durable. Solo una firma inválida, un cuerpo excesivo o un fallo temporal de persistencia producen una respuesta no exitosa. Después del acuse, una cola divide el sobre en Webhook Events, resuelve cada uno por `phone_number_id` y aplica idempotencia antes de modificar datos operativos.

Los eventos pueden terminar como `processed`, `unresolved`, `unsupported`, `blocked`, `uncorrelated` o `failed`. Los desconocidos o todavía no soportados se conservan sin solicitar reintentos inútiles a Meta; los fallos internos usan reintentos y replay propios. El payload crudo se retiene 30 días y no se expone en interfaces tenant-aware.

Un Contact se identifica por `Account + wa_id` y se comparte entre los números del tenant. Una Conversation se identifica por `Account + WhatsApp Phone Number Connection + Contact`: responder siempre usa la conexión del hilo, mientras broadcasts, automatizaciones y nuevas conversaciones fijan explícitamente su conexión. Un Account puede designar como máximo una conexión activa predeterminada, sin fallback silencioso.

El MVP usa un asistente guiado para validar credenciales, suscribir el WABA, registrar números cuando corresponda y observar una entrega real antes de declarar la conexión Active. Embedded Signup, Solution Partner, webhook overrides, mTLS, allowlists estáticos de IP, sincronización histórica y un dashboard completo de deliveries quedan fuera del MVP.

## Alternativas consideradas

### Un endpoint u override por tenant

Se rechaza para el MVP porque `phone_number_id` ya ofrece una clave de routing estable y los endpoints individuales aumentan configuración, secretos y superficie operativa. Los overrides siguen disponibles como evolución para una futura operación como Solution Partner.

### Un solo número por Account

Se rechaza porque impide separar ventas, soporte o sucursales y contradice la identidad que Meta incluye en cada evento. Mantener el límite trasladaría el rediseño a conversaciones, campañas y automatizaciones más adelante.

### Procesar sincrónicamente antes de responder `200`

Se rechaza porque acopla la disponibilidad de todos los consumidores al contrato de entrega de Meta y convierte fallos internos en duplicados externos. La persistencia durable seguida de procesamiento asíncrono permite retries, deduplicación y replay controlados por Wacrm.

### Embedded Signup desde el inicio

Se rechaza porque el proyecto apunta primero a instalaciones directas y no necesita asumir App Review avanzado ni las responsabilidades de un Solution Partner. El asistente manual guiado cubre el MVP sin cerrar esa evolución.

## Consecuencias

- La restricción de una configuración por Account debe reemplazarse por Integration, WABA Subscription y Phone Number Connections.
- La unicidad de Conversation debe incluir la conexión de WhatsApp.
- `processed_at` de una Delivery solo se establece después de clasificar todos sus eventos, no durante el ingreso.
- Los mensajes entrantes se deduplican por ID de Meta; los estados y otros eventos usan sus claves naturales o un fingerprint determinista.
- Desconectar un número conserva sus datos históricos y bloquea nuevos efectos hasta reconectarlo; no desuscribe un WABA que aún tenga números activos.
- La operación inicial usa reintentos automáticos, logs estructurados y comandos Artisan de inspección/replay, no un dashboard especializado.
