# Domain Docs

**Single-context** layout. One domain — the whole repo shares one glossary and one set of ADRs.

## Files

- `CONTEXT.md` — ubiquitous language glossary at the repo root. `/grill-with-docs` writes this during grilling sessions.
- `docs/adr/` — architecture decision records, one file per decision (e.g. `0001-adopt-adr-pattern.md`).

## Consumer rules

Skills that read domain docs (`grill-with-docs`, `to-spec`, `to-tickets`, `implement`, `triage`, `code-review`, `qa`) load:

1. `CONTEXT.md` at the repo root
2. All `*.md` files under `docs/adr/`
