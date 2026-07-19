# WACRM monorepo

Two apps: the repo root is a **Next.js** app (`src/`); `laravel/` is a **Laravel 13 + Inertia v3 + React** app with its own `CLAUDE.md` containing a skill index — read it before touching anything under `laravel/`. The rules below apply to the root Next.js app only.

<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

## Skills (root)

- `react-grab` — only for the continuous "watch my grabs" clipboard loop; not for a one-off pasted grab.
