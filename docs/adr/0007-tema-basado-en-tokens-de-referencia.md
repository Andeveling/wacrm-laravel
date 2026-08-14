# 0007 — Tema basado en tokens de referencia

- **Estado:** Aceptado
- **Fecha:** 2026-08-14

## Contexto

La aplicación tenía tokens HSL propios y una preferencia `light` / `dark` / `system` basada en la clase `.dark`. La renovación visual requiere usar los tokens OKLCH, superficies y cinco acentos definidos en `original-wacrm/src/app/globals.css`, sin perder la preferencia de apariencia ya guardada.

## Decisión

La aplicación usará los tokens de `original-wacrm/src/app/globals.css` como fuente de verdad visual. El modo resuelto se aplicará con `data-mode` y el acento con `data-theme`; se persistirán por dispositivo. La opción existente `system` se conserva y se traduce al modo resuelto. Los valores iniciales para quien no tenga preferencias serán `dark` y `violet`.

## Consecuencias

- Las utilidades y componentes deben consumir tokens semánticos (`background`, `card`, `primary`, etc.) y no colores heredados.
- Ajustes expondrá el selector de modo y los cinco acentos; autenticación sólo mostrará un control compacto de modo.
- La apariencia guardada previamente se conserva al migrar, aunque el mecanismo de aplicación cambie de clase a atributos de `html`.
