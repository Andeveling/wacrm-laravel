# 0009 — Módulos de pantalla por producto

- **Estado:** Aceptado
- **Fecha:** 2026-08-16
- **Contexto:** wacrm — frontend Inertia / React

## Contexto

Las pantallas grandes de Settings empiezan como un solo archivo que mezcla formulario, lista, dialogs y mutaciones Inertia. WhatsApp ya cruzó ese umbral: el control plane de números, remediación y webhook era ilegible, y un TypeError de producción salió de esa pantalla. Contacts y accounts ya viven como módulos de pantalla; el resto de Settings no.

Un Provider o un kit de compound components resolvería el cableado, pero una sola página no tiene consumidores que justifiquen contexto global. Clean Architecture en React añadiría carpetas sin un seam que el operador pueda observar.

## Decisión

Una pantalla grande vive en un **módulo de pantalla** nombrado por el producto (`resources/js/modules/whatsapp/`), no por Settings y no por el namespace PHP. La página Inertia (`resources/js/pages/settings/whatsapp.tsx`) solo reexporta.

El módulo se compone así:

- **contracts** — tipos del borde Inertia (conexión, readiness, issue, props de página). El nombre de cable HTTP se conserva en el borde (`legacyIssues`); los identificadores TypeScript nuevos hablan el lenguaje de producto.
- **model** — reglas puras (pasos de readiness, default activo, kind → variante). Si la lógica es real, lleva un Vitest al estilo de contacts/accounts.
- **hook** (opcional) — mutaciones Inertia y el mutex de ocupado. Las piezas reciben callbacks.
- **ui** — una screen que orquesta y piezas presentacionales compuestas por props y children.

Read-only se compone: la screen no monta UI de escritura cuando el operador no puede gestionar. Las tarjetas no reciben un booleano `canManage`; las acciones llegan como children.

No hay `createContext` / Provider para una sola página. No hay capas Clean Architecture en JavaScript. Cada pieza de formulario llama `useId()` para `htmlFor` / `id`.

La convención aplica primero a WhatsApp. El resto de pantallas la adoptan al tocarse, la misma regla gradual de ADR 0001.

## Alternativas consideradas

### Dejar Settings como un archivo por pantalla

Se rechaza para WhatsApp porque el archivo ya mezcla cuatro flujos y el operador no puede cambiar uno sin leer los otros. Sigue válido para pantallas chicas.

### Un Provider / React context por pantalla

Se rechaza porque un solo consumidor no justifica un bus implícito. Inbox, contacts y accounts ya componen por props; un contexto escondería el grafo que el siguiente agente necesita ver.

### Carpetas Clean Architecture en React

Se rechaza porque no hay un seam observable del otro lado: el operador ve Inertia y el DOM, no use-cases. Contacts y accounts ya marcan la profundidad suficiente.

## Consecuencias

- El siguiente agente que toque una pantalla grande sigue WhatsApp, no inventa otro monolito.
- Los tests existentes de Feature y Browser siguen siendo el contrato de producto; Vitest cubre solo el model.
- Renombrar un prop de Inertia o un `data-testid` de cable no forma parte de extraer el módulo.
