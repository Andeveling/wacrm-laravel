# JS/TS tooling

Linter + formatter: **Biome 2.5** (`@biomejs/biome`). Single config at `biome.json`. ESLint and Prettier are gone — do not reintroduce them. Type checking is a separate tool: `pnpm types:check` (`tsc --noEmit`). The rest of the scripts are in `package.json`.

Ignored paths (consistent with the old ESLint config):
`vendor`, `node_modules`, `public`, `bootstrap/ssr`, `public/favicon.svg`, `tailwind.config.js`, `vite.config.ts`, `resources/js/actions/**`, `resources/js/components/ui/*`, `resources/js/routes/**`, `resources/js/wayfinder/**`.

Excluded from formatter, linter, and assist: `resources/js/components/ui/*` (shadcn primitives stay as the CLI wrote them). `resources/views/mail/*` is excluded from the formatter.

Rules disabled in `biome.json` match the baseline the previous ESLint config enforced, so the Laravel starter scaffold does not produce false positives: `noExplicitAny`, `noArrayIndexKey`, `noSvgWithoutTitle`, `useSemanticElements`, `useButtonType`, `useAriaPropsSupportedByRole`, `noDangerouslySetInnerHtml`, `noBlankTarget`. Re-enable per-file when the underlying pattern is fixed.

Tailwind v4 `@source` / `@theme` / `@apply` directives in `resources/css/app.css` are recognized via `css.parser.tailwindDirectives`.

`biome.json` is parsed as strict JSON — a `//` comment there makes Biome abandon the config and scan `vendor/`. Rationale goes here, not in the file.

## React rules

Biome's `react` domain is auto-detected from `package.json`; `linter.domains.react: "recommended"` states it explicitly. On top of the domain defaults:

| Rule | Level | Why |
| --- | --- | --- |
| `noReactForwardRef` | error | React 19 passes `ref` as a plain prop. |
| `noNestedComponentDefinitions` | error | Bails the React Compiler out of memoizing the parent. |
| `noReactPropAssignments` | error | Mutating props breaks the Compiler's assumptions. |
| `useComponentExportOnlyModules` | error | A non-component export kills Fast Refresh for the file. |
| `noLeakedRender` | warn → error in contacts | `{count && <X/>}` renders a literal `0`. |
| `useUniqueElementIds` | warn → error in contacts | Hardcoded `id` breaks when a component mounts twice; use `useId()`. |

`performance/noJsxPropsBind` stays **off**. It bans inline arrow props, which is exactly what the React Compiler (enabled in `vite.config.mts`) already memoizes — turning it on would reintroduce the manual `useCallback` the Compiler removed. Do not add `useMemo`/`useCallback` for render-cost reasons either.

### The contacts ratchet

`resources/js/modules/contacts/**` is the reference module: rules the rest of the codebase still only warns about are blocking there, and the a11y rules disabled globally are back on. Its override lives at the bottom of `biome.json`. Clean up a module, then add its path to that `includes` list.
