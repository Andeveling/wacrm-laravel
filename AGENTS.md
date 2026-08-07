# Laravel app — skill index

Laravel 13 + Inertia v3 + React 19 + Tailwind v4, running under Sail. Package versions and framework conventions are injected automatically by Laravel Boost — do not duplicate them here.

`original-wacrm/` is the archived Next.js + Supabase app this project migrates away from. It is reference material only: read it to check original behaviour, never edit it, and never wire the two together.

**Rule: activate the matching skill BEFORE writing code in its domain — not when stuck.** A task spanning several domains activates each skill as you enter it. Why: these stacks (Laravel 13, Inertia v3, Tailwind v4, Wayfinder) have breaking changes vs. training data; the skills carry the current APIs.

| Skill | When | Why it matters |
| --- | --- | --- |
| `laravel-best-practices` | Any backend PHP: controllers, models, migrations, form requests, policies, jobs, Eloquent queries | Catches N+1s, authz gaps, and Laravel-13-specific patterns |
| `fortify-development` | Anything auth: login, register, 2FA, passkeys, password reset, `app/Actions/Fortify/` | Fortify owns all auth routes/controllers here. NOT for Passport or Socialite |
| `inertia-react-development` | React pages, forms, navigation in `resources/js/` | Inertia v3 removed axios and `Inertia::lazy()`; hooks like `useHttp` are new |
| `wayfinder-development` | Any time frontend code calls a backend route or controller | Use generated `@/actions` / `@/routes` functions, never hardcoded URLs |
| `tailwindcss-development` | Writing or fixing utility classes in JSX/Blade | Tailwind v4 syntax differs from v3 |
| `echo-development` | Broadcasting, WebSockets, Reverb, presence channels, `ShouldBroadcast` | Channel auth and Echo config are easy to get subtly wrong |
| `ai-sdk-development` | `Laravel\Ai\` namespace or any AI feature of this app | First-party SDK, v0 — API not in training data |

## Git hooks — the standard is enforced, not requested

**Lefthook** runs them; `pnpm install` and `composer install` both arm it. Config is `lefthook.yml` — edit that, never `.git/hooks/`.

`pre-commit`, on staged files only, all jobs in parallel (~1.5–3s):

| Job | Files | Behaviour |
| --- | --- | --- |
| `pint` | `*.php` | **fixes and re-stages** |
| `biome` | `*.{ts,tsx,js,jsx,mjs,mts,css,json}` | **fixes and re-stages** |
| `no-inline-fqcn` | `*.php` | blocks |
| `adr-layers` | `*.php` | blocks |
| `test-layout` | `*.php` | blocks |
| `phpstan` | `*.php` | blocks |
| `tsc --noEmit` | `*.{ts,tsx}` | blocks |
| `check-ui-language` | `*.{ts,tsx}` | blocks |

`commit-msg` runs **commitlint** (`commitlint.config.mjs`): Conventional Commits, subject ≤72 chars, English imperative. Merge and revert subjects pass through.

Formatting never costs a round trip — Pint and Biome repair the staged content in place. The other six report and block; fix the cause and re-commit.

**Do not use `--no-verify` or `LEFTHOOK=0`.** Bypassing only moves the failure to CI, which runs the full suite anyway.

Four jobs read the working tree rather than the index, because none has a per-file mode that still resolves the project as a whole: `adr-layers`, `test-layout`, `phpstan` and `tsc`. A commit built with `git add -p` is therefore checked against unstaged changes too.

`adr-layers` (`tools/lint/adr-layers.php`, also `composer lint:adr-layers`) is what makes ADR 0001 binding rather than advisory — read that table below as enforced, not aspirational. It boots the framework to read the real route table, touches no database, and costs ~0.2s:

1. No class under `app/` is named `*Controller`.
2. Every route handled by app code points at `App\Domain\<Contexto>\Actions\<X>` — a `Class@method` reference fails too.
3. Actions are `final` and expose only `__invoke`.
4. Responders are `final`, named `*Responder`, and import neither `App\Models` nor `Illuminate\Database`.
5. Results are `final readonly`, named `*Result`, and import neither `Illuminate\Http` nor `Inertia`.
6. A bounded context contains only `Actions`, `Responders`, `Results`, `Services`, `Support`. A new layer needs an ADR first.

`test-layout` (`tools/lint/test-layout.php`, also `composer lint:test-layout`) does the same for ADR 0004. Pure filesystem plus `phpunit.xml` — no framework boot:

1. Every directory under `tests/` is a testsuite registered in `phpunit.xml` or a support directory (`Concerns`, `Fixtures`). A stray one holds tests nobody runs.
2. Every file inside a suite is named `*Test.php` — Pest skips the rest silently.
3. `Domain` and `Unit` never touch the database: no `RefreshDatabase` and friends, no `::factory()`, no `assertDatabase*`.
4. No `*ControllerTest` — app/ has no controllers, so the name is stale.
5. `tests/Feature/<X>` and `tests/Domain/<X>` name a real bounded context under `app/Domain`, or one of the cross-cutting seams `Api`, `Auth`, `Concerns`, `Jobs`.

Rule 3 stops one degree short of ADR 0004, which also asks that `Domain` and `Unit` not boot Laravel. `Domain` already meets that — `tests/Pest.php` binds `TestCase` to `Feature` and `Unit` only — but several `Unit` tests do need the container (config, `resource_path()`), so the enforced line is the database. Tightening it means moving those first.

If the hooks stop firing, run `pnpm exec lefthook install`.

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

- **Issues**: GitHub Issues on `github.com/Andeveling/wacrm-laravel` — see `docs/agents/issue-tracker.md`.
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/ai (AI) - v0
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- laravel-echo (ECHO) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail pnpm run build`, `vendor/bin/sail pnpm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail pnpm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail pnpm run build` or ask the user to run `vendor/bin/sail pnpm run dev` or `vendor/bin/sail composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `vendor/bin/sail artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `vendor/bin/sail artisan make:test --pest SomeFeatureTest` instead of `vendor/bin/sail artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `vendor/bin/sail artisan test --compact` or filter: `vendor/bin/sail artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
