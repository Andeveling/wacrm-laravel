# wacrm

Multi-tenant WhatsApp CRM. Every operational record belongs to an `Account` (the tenant). Laravel 13 + Inertia v3 + React 19 + Tailwind v4, under Sail.

`original-wacrm/` is the archived Next.js + Supabase app this project migrates away from. Reference material only: read it to check original behaviour, never edit it, never wire the two together.

Activate the matching skill before writing code in its domain — not when stuck.

Answer structural questions (repo map, call flow, dependencies, impact) with CodeGraph before broad Glob/Grep. Run `codegraph init` once if `.codegraph/` is missing rather than falling back.

Commits run Lefthook and commitlint. Never `--no-verify` or `LEFTHOOK=0` — it only moves the failure to CI.

## Where to look

| Topic | File |
| --- | --- |
| Which skills exist, where they live, how they are vendored | `docs/agents/skills.md` |
| Commit hooks, blocking lints, ADR 0001 and 0004 enforcement | `docs/agents/git-hooks.md` |
| Biome config, ignored paths, disabled rules | `docs/agents/js-tooling.md` |
| Issue tracker | `docs/agents/issue-tracker.md` |
| Triage labels | `docs/agents/triage-labels.md` |
| Domain glossary and ADRs | `CONTEXT.md`, `docs/adr/`, `docs/agents/domain.md` |
