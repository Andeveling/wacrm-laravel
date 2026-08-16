# 0010 — Primitivas shadcn sobre Base UI

- **Estado:** Aceptado
- **Fecha:** 2026-08-16
- **Contexto:** wacrm — frontend Inertia / React

## Contexto

El kit en `resources/js/components/ui` usa estilo `new-york` sobre Radix (`@radix-ui/react-*` y el paquete unificado `radix-ui`). `original-wacrm` ya está en `base-nova` / `@base-ui/react`. Las APIs no son drop-in: Base UI reemplaza `asChild` por `render`, y Select/Menu tienen quirks distintos (`onValueChange(null)` al deseleccionar; `Menu.GroupLabel` exige `Menu.Group`).

Los tokens visuales siguen siendo ADR 0007. Esto no es un rediseño.

## Decisión

Las primitivas interactivas del kit pasan a Base UI vía CLI: `components.json` `style` → `base-nova`, luego `shadcn add --overwrite` de esos wrappers. No se copian los archivos de `original-wacrm` (Next/`rsc: true`).

En el mismo PR se reescriben los call sites de Dialog, Sheet, Select, DropdownMenu, Popover y Tooltip de `asChild` a `render`. Sin shim. Paridad de comportamiento el día 1 en esas superficies.

Quedan fuera de este corte: átomos HTML (`button`, `input`, `card`, …), `sidebar.tsx`, y `@radix-ui/react-slot` (`Button`/`Badge`/`Breadcrumb` `asChild`). “Cero Radix” es otro cambio.

## Alternativas consideradas

### Quedarse en Radix / `radix-nova`

Se rechaza porque el siguiente `shadcn add` y el port de `original-wacrm` seguirían en dos primitivas. Radix no está roto; el costo es de alineación, no de producto.

### Copiar `original-wacrm/src/components/ui`

Se rechaza: ese árbol asume Next y `rsc: true`. Los quirks se corrigen en call sites.

### Convivir Radix y Base UI por oleadas

Se rechaza: `pipelines-screen` ya tiene `data-[popup-open]` inerte bajo Radix. Dos primitivas dejan selectores a medias.

### Shim de `asChild` en los wrappers

Se rechaza: es deuda. El corte limpio es `render`.

## Consecuencias

- Un PR posterior puede quitar Slot cuando se adopte el Button de `base-nova`.
- Inbox Select (`undefined` = vacío) y menús con `DropdownMenuLabel` hay que revisarlos contra los quirks de Base UI.
- No hay término de dominio nuevo: esto no entra en `CONTEXT.md`.
