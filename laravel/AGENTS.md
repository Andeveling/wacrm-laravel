# Laravel app — skill index

Laravel 13 + Inertia v3 + React 19 + Tailwind v4, running under Sail. Package versions and framework conventions are injected automatically by Laravel Boost — do not duplicate them here.

**Rule: activate the matching skill BEFORE writing code in its domain — not when stuck.** A task spanning several domains activates each skill as you enter it. Why: these stacks (Laravel 13, Inertia v3, Tailwind v4, Wayfinder) have breaking changes vs. training data; the skills carry the current APIs.

| Skill | When | Why it matters |
|---|---|---|
| `laravel-best-practices` | Any backend PHP: controllers, models, migrations, form requests, policies, jobs, Eloquent queries | Catches N+1s, authz gaps, and Laravel-13-specific patterns |
| `fortify-development` | Anything auth: login, register, 2FA, passkeys, password reset, `app/Actions/Fortify/` | Fortify owns all auth routes/controllers here. NOT for Passport or Socialite |
| `inertia-react-development` | React pages, forms, navigation in `resources/js/` | Inertia v3 removed axios and `Inertia::lazy()`; hooks like `useHttp` are new |
| `wayfinder-development` | Any time frontend code calls a backend route or controller | Use generated `@/actions` / `@/routes` functions, never hardcoded URLs |
| `tailwindcss-development` | Writing or fixing utility classes in JSX/Blade | Tailwind v4 syntax differs from v3 |
| `echo-development` | Broadcasting, WebSockets, Reverb, presence channels, `ShouldBroadcast` | Channel auth and Echo config are easy to get subtly wrong |
| `ai-sdk-development` | `Laravel\Ai\` namespace or any AI feature of this app | First-party SDK, v0 — API not in training data |

## JS/TS tooling

Linter + formatter: **Biome 2.5** (`@biomejs/biome`). Single config at `biome.json`. ESLint and Prettier are gone — do not reintroduce them.

Scripts (run via `pnpm`):

- `pnpm lint:check` — `biome check` (lint + format check). CI gate. Exits non-zero on errors, warnings are informational.
- `pnpm lint` — `biome check --write` (apply safe autofixes).
- `pnpm format:check` — `biome format` (no writes).
- `pnpm format` — `biome format --write` (apply formatting).
- `pnpm types:check` — `tsc --noEmit` (type check, separate tool).

Ignored paths (consistent with the old ESLint config):
`vendor`, `node_modules`, `public`, `bootstrap/ssr`, `public/favicon.svg`, `tailwind.config.js`, `vite.config.ts`, `resources/js/actions/**`, `resources/js/components/ui/*`, `resources/js/routes/**`, `resources/js/wayfinder/**`.

Excluded from formatter but linted: `resources/js/components/ui/*` (shadcn-style scaffold) and `resources/views/mail/*` (blade templates).

Rules disabled in `biome.json` match the baseline the previous ESLint config enforced (no false positives on the Laravel starter scaffold): `noExplicitAny`, `noArrayIndexKey`, `noSvgWithoutTitle`, `useSemanticElements`, `useButtonType`, `useAriaPropsSupportedByRole`, `noDangerouslySetInnerHtml`, `noBlankTarget`. Re-enable per-file when the underlying pattern is fixed.

Tailwind v4 `@source` / `@theme` / `@apply` directives in `resources/css/app.css` are recognized via `css.parser.tailwindDirectives`.

## Workflow references

- **Issues**: GitHub Issues on `github.com/Andeveling/wacrm` — see `docs/agents/issue-tracker.md`.
- **Triage labels**: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix` — see `docs/agents/triage-labels.md`.
- **Domain docs**: single `CONTEXT.md` + `docs/adr/` — see `docs/agents/domain.md`.

## CodeGraph

When answering structural or codebase questions, use CodeGraph before broad filesystem searches. This is a hard ordering rule for repo maps, architecture, call flow, dependencies, symbol references, impact analysis, and “how does X work” questions.

Required order for structural/codebase questions:

1. Resolve the project root with `git rev-parse --show-toplevel || pwd`.
2. Confirm the root is a real project/workspace. Do not initialize CodeGraph in `$HOME`, temporary directories, or non-project folders.
3. Check for `<project-root>/.codegraph/` before any broad Read/Glob/Grep filesystem exploration.
4. If `.codegraph/` is missing and CodeGraph is enabled/available, immediately run `codegraph init <project-root>` once, then use the `codegraph_explore` MCP tool or `codegraph explore "..."`.
5. Do not fall back just because `.codegraph/` is missing; a missing index is the trigger to lazy-initialize, not a reason to skip CodeGraph.
6. Only fall back to normal filesystem tools after CodeGraph init or CodeGraph use fails, and briefly explain the fallback.

Broad Read/Glob/Grep exploration before this CodeGraph check is explicitly discouraged for structural/codebase questions.

## Rules

- Never use imports inline \App\Http\Controllers\Invitations\StoreInvitationController::class.
- Allway use import using use
