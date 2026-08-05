# ADR 0003: Autenticación MCP vía API Key guard existente

**Estado:** Accepted
**Fecha:** 2026-07-19

## Contexto

Laravel MCP v0.8.2 ofrece dos caminos documentados de autenticación:

1. **OAuth 2.1 con Passport** — el estándar del spec MCP, requiere instalar `laravel/passport`, configurar OAuth server, publicar vistas de autorización.
2. **Sanctum** — bearer tokens simples, requiere `HasApiTokens` en el modelo User.

El proyecto no tiene ninguno instalado. Tiene un sistema de API keys propio (`app/Models/ApiKey`, guard `custom-apikey`, middleware `AuthenticateApiKey`) probado en producción con:
- Tokens `wacrm_{live|test}_<64 hex chars>`
- SHA-256 hashing (solo el hash se persiste)
- Scopes granulares (`ApiScope`)
- Audit trail (`api_key_requests`)
- Rate limiting por key, no por IP
- Tenant scoping automático vía `BelongsToAccount`

## Decisión

**Reutilizar el guard `api_key` existente** para autenticar el servidor MCP.

Se creó un middleware dedicado `AuthenticateMcp` (`auth.mcp`) que comparte la misma lógica de resolución del guard pero omite el audit trail (los tools MCP son solo lectura, no ameritan `api_key_requests` rows). El middleware bindea el `account_id` en `AccountScope`, por lo que todos los tools MCP heredan el tenant scoping sin código adicional.

## Alternativas consideradas

### Passport OAuth 2.1
- **A favor:** es el estándar del spec MCP, máxima compatibilidad con clientes MCP.
- **En contra:** requiere instalar Passport, migraciones, claves RSA, vistas de autorización, y mantener dos sistemas de autenticación en paralelo sin una necesidad clara de OAuth. Passport agrega complejidad de operación (rotación de claves, client registration) que el caso de uso actual no justifica.

### Sanctum
- **A favor:** más simple que Passport, solo requiere `HasApiTokens`.
- **En contra:** crearía un segundo esquema de tokens (Sanctum tokens + API keys del CRM) sin beneficio — los clientes que ya usan `wacrm_*` tokens tendrían que migrar, y los nuevos clientes MCP tendrían un formato de token distinto al de la REST API.

### API key existente (elegida)
- **A favor:** un solo formato de token para toda la plataforma (REST API + MCP), sin dependencias nuevas, sin migraciones, consistente con la experiencia del desarrollador.
- **En contra:** no es OAuth — clientes MCP que solo soporten OAuth no podrán conectarse. Este riesgo se acepta porque el caso de uso primario es Laravel Boost y clientes internos que sí pueden usar bearer tokens.

## Consecuencias

- **Un solo token por account** sirve tanto para REST API como para MCP.
- Si en el futuro se necesita OAuth (ej: clientes MCP de terceros que solo acepten OAuth), se puede agregar Passport como capa adicional sin romper el guard `api_key` existente.
- El middleware `auth.mcp` es deliberadamente más ligero que `AuthenticateApiKey` (sin audit trail) — si se agregan tools de escritura en el futuro, habrá que reevaluar.
