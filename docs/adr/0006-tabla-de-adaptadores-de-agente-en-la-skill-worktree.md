# 0006 — Tabla de adaptadores de agente en la skill worktree

- **Estado:** Aceptado
- **Fecha:** 2026-08-08
- **Contexto:** tooling de desarrollo (`skills/worktree/`), no dominio de la aplicación

## Contexto

La skill `worktree` cableaba `opencode run -i --command implement` al final de su script de creación. En esta máquina hay cuatro agentes instalados y **no comparten interfaz de invocación**, verificado con `--help` en cada uno:

| Agente | Interfaz | Comandos con nombre |
| --- | --- | --- |
| `opencode` | `run -i --command <cmd> --model --variant <args>` | sí |
| `claude` | `claude [prompt]`, interactivo por defecto | vía slash command en el prompt |
| `pi` | `[opciones] [mensajes...]`, `--model`, `--thinking` | no |
| `omp` | `[flags] [mensajes...]`, `--model` difuso | no |

Herdr ya es agnóstico (`herdr pane run <pane-id> <command...>` acepta cualquier comando), así que el acoplamiento era enteramente de la skill.

## Decisión

La skill mantiene una **tabla de adaptadores** —un `case` dentro de `create.sh`— que traduce `(agente, issue)` a una línea de comando concreta. No se pasa el mismo prompt en lenguaje natural a los cuatro.

`pi` y `omp` comparten rama porque comparten interfaz de verdad, no por atajo. Son dos formas de invocación repartidas entre cuatro agentes.

El agente es un argumento **obligatorio** del script; `none` es un valor válido que deja el worktree aprovisionado y frío. La recomendación por defecto (`opencode`) vive en la prosa de `SKILL.md`, no en el script: así el script nunca elige en silencio y la recomendación cambia sin tocar código.

Solo los cuatro agentes de la lista blanca son válidos. `WORKTREE_AGENT_MODEL` y `WORKTREE_AGENT_VARIANT` aplican únicamente a `opencode`, el único cuya interfaz les da significado.

## Alternativas rechazadas

- **Prompt uniforme para todos.** Un solo camino, sin tabla, pero pierde el enganche con los comandos con nombre de `opencode` — que es justo el camino hoy probado. La asimetría no desaparece: se vuelve invisible y falla dentro del pane.
- **Restringir la elección a agentes con comandos con nombre.** Lo más simple, y deja un solo agente elegible después de un requisito escrito sobre elegir entre cuatro.
- **Aceptar cualquier binario del PATH con la plantilla genérica.** Adivinar `<binario> "<prompt>"` funciona hasta el primer CLI que espera un subcomando, y ese fallo aparece lejos del punto de invocación.

## Consecuencias

- La tabla envejece con cada cambio de CLI ajeno. Es el costo aceptado: envejece igual bajo el prompt uniforme, solo que sin aviso.
- El agente se valida con `command -v` **antes** de crear el worktree, y al pane se le pasa la **ruta absoluta ya resuelta**. `pi` y `codegraph` viven en rutas efímeras de fnm (`/run/user/<uid>/fnm_multishells/...`) que no sobreviven al shell: el nombre pelado no es confiable en otro proceso.
- Los criterios de completitud de la skill pasan a ser condicionales — con `none` no hay proceso de agente — y la respuesta final reporta qué agente se lanzó.
- La verificación es manual, fuera de las cuatro suites de `phpunit.xml`.
