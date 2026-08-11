# 0006 — Cuatro pilares de calidad para desarrollo agéntico

- **Estado:** Aceptado
- **Fecha:** 2026-08-11
- **Contexto:** wacrm

## Contexto

El proyecto ya ejecuta pruebas Pest, Vitest y Browser, análisis estático, formateo y reglas estructurales derivadas de los ADR 0001 y 0004. Sin embargo, una suite verde no demuestra por sí sola que el desarrollo haya seguido TDD, que los tests detecten regresiones reales ni que la arquitectura conserve módulos profundos y límites coherentes. Tampoco existían gates de cobertura, CRAP, duplicación o mutation testing, y `main` podía recibir cambios sin checks requeridos.

## Decisión

Todo cambio productivo se evalúa mediante cuatro pilares complementarios:

1. **Testing y TDD.** El agente registra evidencia reproducible del ciclo Red, Green y Refactor. Cada comportamiento modificado conserva una prueba permanente en el seam público más alto definido por ADR 0004. Pest mide cobertura PHP, Vitest mide cobertura TypeScript y Codecov exige al menos 90 % sobre líneas modificadas. La cobertura global parte de su baseline medido y solo puede mantenerse o subir.
2. **Calidad de código.** Pest genera CRAP4J para `app/Domain`: un método falla con CRAP igual o superior a 30 o complejidad ciclomática superior a 10, y advierte desde complejidad 8. `jscpd` mide duplicación en PHP y TypeScript; el baseline existente no puede crecer y no se aceptan clones nuevos de al menos 5 líneas o 50 tokens.
3. **Mutation testing.** Pest 5 es el motor PHP, inicialmente sobre `app/Domain`. Las pruebas declaran su intención mediante `covers()` o `mutates()`. El workflow completo corre de forma nocturna y evoluciona desde un baseline informativo hasta MSI mínimo 80; una vez estable, las clases modificadas se mutan también como gate de pull request. El frontend adopta mutation testing en una fase posterior.
4. **Revisión arquitectónica.** Pest Arch y los linters de los ADR bloquean violaciones deterministas. Además, un agente independiente revisa dirección de dependencias, aislamiento por `Account`, cohesión y profundidad de módulos, fugas de framework, contratos públicos, fuentes de verdad, seams de pruebas, complejidad y límites frontend. La revisión publica findings resolubles y alimenta un check requerido `architecture-review`; una opinión inicial no es un veto inapelable.

Pest 5 es el núcleo PHP. Se conservan los linters específicos porque inspeccionan contratos que Pest Arch no conoce, como la tabla real de rutas. Codecov cubre el diff multilenguaje y `jscpd` la duplicación que Pest no mide. Pest Agent se permite como sonda temporal de feedback, nunca como sustituto de una prueba permanente. PHPStan analiza también las pruebas mediante su integración oficial con Pest.

Los gates excluyen código generado, dependencias, snapshots, scaffolds explícitamente identificados y `original-wacrm/`. Cualquier otra excepción vive en una allowlist central con motivo, issue de seguimiento y caducidad; no se aceptan ignores locales libres. Los baselines solo pueden mejorar. Reducir cobertura o MSI, elevar duplicación o relajar un límite requiere justificar la revisión de este ADR, no solo cambiar configuración.

`main` queda protegida cuando los nuevos gates estén verdes: sin push directo, con `ci`, `browser` y `architecture-review` requeridos, rama actualizada y conversaciones resueltas.

## Consecuencias

- La calidad deja de depender de que un agente recuerde recomendaciones: los comportamientos medibles se convierten en gates reproducibles.
- Mutation testing y revisión arquitectónica se introducen por etapas para obtener baselines útiles antes de bloquear trabajo.
- Los reportes de cobertura se comparten con Codecov, un servicio externo, a cambio de evitar mantener un comparador de diffs propio.
- Los checks añaden tiempo y dependencias al pipeline; TIA y sharding se difieren mientras la suite completa siga siendo rápida.
- Este ADR desarrolla la evolución prevista por ADR 0004: mantiene sus seams y añade ahora los porcentajes y verificaciones que antes se habían pospuesto.
