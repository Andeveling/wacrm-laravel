# 0003 — Autenticación MCP con el guard existente de API Key

**Estado:** Aceptado
**Fecha:** 2026-07-19

## Contexto

Laravel MCP v0.8.2 ofrece dos caminos documentados de autenticación:

1. **OAuth 2.1 con Passport** — el estándar del spec MCP, requiere instalar `laravel/passport`, configurar un servidor OAuth y publicar vistas de autorización.
2. **Sanctum** — bearer tokens simples, requiere `HasApiTokens` en el modelo User.

El proyecto no tiene ninguno instalado. Tiene un sistema propio de API keys (`app/Models/ApiKey`, guard `custom-apikey`, middleware `AuthenticateApiKey`) probado en producción con:
- Tokens `wacrm_{live|test}_<64 hex chars>`
- Hashing SHA-256 (solo se persiste el hash)
- Scopes granulares (`ApiScope`)
- Registro de auditoría (`api_key_requests`)
- Rate limiting por key, no por IP
- Scope automático por tenant vía `BelongsToAccount`

## Decisión

**Reutilizar el guard `api_key` existente** para autenticar el servidor MCP.

Se creó un middleware dedicado `AuthenticateMcp` (`auth.mcp`) que comparte la misma lógica de resolución del guard pero omite el registro de auditoría (las herramientas MCP son solo lectura, así que no ameritan registros en `api_key_requests`). El middleware vincula `account_id` en `AccountScope`, por lo que todas las herramientas MCP heredan el scope por tenant sin código adicional.

## Alternativas consideradas

### Passport OAuth 2.1
- **A favor:** es el estándar del spec MCP, máxima compatibilidad con clientes MCP.
- **En contra:** requiere instalar Passport, migraciones, claves RSA, vistas de autorización y mantener dos sistemas de autenticación en paralelo sin una necesidad clara de OAuth. Passport agrega complejidad operativa (rotación de claves, registro de clientes) que el caso de uso actual no justifica.

### Sanctum
- **A favor:** más simple que Passport, solo requiere `HasApiTokens`.
- **En contra:** crearía un segundo esquema de tokens (tokens Sanctum + API keys del CRM) sin beneficio — los clientes que ya usan tokens `wacrm_*` tendrían que migrar, y los nuevos clientes MCP tendrían un formato de token distinto al de la API REST.

### API key existente (elegida)
- **A favor:** un solo formato de token para toda la plataforma (API REST + MCP), sin dependencias nuevas, sin migraciones, consistente con la experiencia de desarrollo.
- **En contra:** no es OAuth — clientes MCP que solo soporten OAuth no podrán conectarse. Este riesgo se acepta porque el caso de uso principal es Laravel Boost y clientes internos que sí pueden usar bearer tokens.

## Consecuencias

- **Un solo token por account** sirve tanto para la API REST como para MCP.
- Si en el futuro se necesita OAuth (por ejemplo, clientes MCP de terceros que solo acepten OAuth), se puede agregar Passport como una capa adicional sin romper el guard `api_key` existente.
- El middleware `auth.mcp` es deliberadamente más liviano que `AuthenticateApiKey` (sin registro de auditoría); si se agregan herramientas de escritura en el futuro, habrá que reevaluarlo.
