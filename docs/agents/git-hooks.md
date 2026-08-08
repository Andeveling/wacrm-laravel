# Git hooks — the standard is enforced, not requested

**Lefthook** runs them; `pnpm install` and `composer install` both arm it. Config is `lefthook.yml` — edit that, never `.git/hooks/`. If the hooks stop firing, run `pnpm exec lefthook install`.

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

## `no-inline-fqcn`

Reference classes through a `use` import, never inline: `StoreInvitationController::class` after importing it, not `\App\Http\Controllers\Invitations\StoreInvitationController::class`.

## `adr-layers`

`tools/lint/adr-layers.php`, also `composer lint:adr-layers`. This is what makes ADR 0001 binding rather than advisory — read the rules as enforced, not aspirational. It boots the framework to read the real route table, touches no database, and costs ~0.2s:

1. No class under `app/` is named `*Controller`.
2. Every route handled by app code points at `App\Domain\<Contexto>\Actions\<X>` — a `Class@method` reference fails too.
3. Actions are `final` and expose only `__invoke`.
4. Responders are `final`, named `*Responder`, and import neither `App\Models` nor `Illuminate\Database`.
5. Results are `final readonly`, named `*Result`, and import neither `Illuminate\Http` nor `Inertia`.
6. A bounded context contains only `Actions`, `Responders`, `Results`, `Services`, `Support`. A new layer needs an ADR first.

## `test-layout`

`tools/lint/test-layout.php`, also `composer lint:test-layout`. Does the same for ADR 0004. Pure filesystem plus `phpunit.xml` — no framework boot:

1. Every directory under `tests/` is a testsuite registered in `phpunit.xml` or a support directory (`Concerns`, `Fixtures`). A stray one holds tests nobody runs.
2. Every file inside a suite is named `*Test.php` — Pest skips the rest silently.
3. `Domain` and `Unit` never touch the database: no `RefreshDatabase` and friends, no `::factory()`, no `assertDatabase*`.
4. No `*ControllerTest` — app/ has no controllers, so the name is stale.
5. `tests/Feature/<X>` and `tests/Domain/<X>` name a real bounded context under `app/Domain`, or one of the cross-cutting seams `Api`, `Auth`, `Concerns`, `Jobs`.

Rule 3 stops one degree short of ADR 0004, which also asks that `Domain` and `Unit` not boot Laravel. `Domain` already meets that — `tests/Pest.php` binds `TestCase` to `Feature` and `Unit` only — but several `Unit` tests do need the container (config, `resource_path()`), so the enforced line is the database. Tightening it means moving those first.
