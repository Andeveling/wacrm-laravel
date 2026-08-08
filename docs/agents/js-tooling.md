# JS/TS tooling

Linter + formatter: **Biome 2.5** (`@biomejs/biome`). Single config at `biome.json`. ESLint and Prettier are gone — do not reintroduce them. Type checking is a separate tool: `pnpm types:check` (`tsc --noEmit`). The rest of the scripts are in `package.json`.

Ignored paths (consistent with the old ESLint config):
`vendor`, `node_modules`, `public`, `bootstrap/ssr`, `public/favicon.svg`, `tailwind.config.js`, `vite.config.ts`, `resources/js/actions/**`, `resources/js/components/ui/*`, `resources/js/routes/**`, `resources/js/wayfinder/**`.

Excluded from formatter but linted: `resources/js/components/ui/*` (shadcn-style scaffold) and `resources/views/mail/*` (blade templates).

Rules disabled in `biome.json` match the baseline the previous ESLint config enforced, so the Laravel starter scaffold does not produce false positives: `noExplicitAny`, `noArrayIndexKey`, `noSvgWithoutTitle`, `useSemanticElements`, `useButtonType`, `useAriaPropsSupportedByRole`, `noDangerouslySetInnerHtml`, `noBlankTarget`. Re-enable per-file when the underlying pattern is fixed.

Tailwind v4 `@source` / `@theme` / `@apply` directives in `resources/css/app.css` are recognized via `css.parser.tailwindDirectives`.
