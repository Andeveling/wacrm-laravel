# 0004 — Pruebas en seams públicos con PostgreSQL

- **Estado:** Aceptado
- **Fecha:** 2026-08-05
- **Contexto:** wacrm

## Contexto

El proyecto combina Laravel, Inertia, React, herramientas MCP y comportamiento específico de PostgreSQL. Probar cada capa por separado duplicaría casos y acoplaría la suite a la implementación. Usar SQLite para las pruebas con persistencia daría un entorno más rápido, pero no verificaría particiones, `jsonb`, búsqueda de texto, pgvector ni otros contratos reales del sistema.

## Decisión

Cada comportamiento se prueba una sola vez en el seam público más alto que permita observarlo con confianza:

- HTTP o Inertia para web y API.
- El endpoint MCP para el contrato MCP.
- Browser para interacciones críticas de React.
- Vitest en Node para lógica TypeScript pura.
- Domain o Unit para reglas PHP puras, sin iniciar Laravel ni usar base de datos.

Toda prueba que use persistencia se ejecuta con PostgreSQL y pgvector. SQLite no sustituye este contrato.

CI mantiene dos gates obligatorios en cada pull request: uno rápido para calidad estática, Unit, Domain, Feature y Vitest; y otro separado para Browser con Playwright. El `pre-commit` permanece limitado a formato, lint, análisis estático y tipos.

## Consecuencias

- La suite evita repetir el mismo comportamiento en controllers, Actions, Responders y componentes.
- CI es más pesado que una configuración basada en SQLite, pero representa el entorno real del producto.
- Los fallos de Browser quedan aislados y siguen bloqueando la integración.
- No se incorporan por ahora porcentajes mínimos de cobertura, `jsdom` ni Testing Library. Se añadirán solo cuando un comportamiento acordado no pueda cubrirse adecuadamente con los seams existentes.
