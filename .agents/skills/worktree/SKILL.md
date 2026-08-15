---
name: worktree
description: Use when the user asks to create, list, review, open, remove, test, start, or stop a Git worktree in this project; always manage it through Herdr with isolated Sail services.
---

# Worktree

Manage project worktrees with Herdr. The same request must follow the same process every time.

## Hard rule

Use `herdr` for every worktree operation. Verify `HERDR_ENV=1` before any Herdr command. If the check fails, stop and report that the current session is not running inside Herdr; do not fall back to `git worktree`.

Resolve the repository root before operating:

```bash
git rev-parse --show-toplevel
```

Keep the source checkout untouched. Do not copy uncommitted files into a new worktree.

## Arguments

Interpret the user's request as one of these modes:

- `create <issue>`: create a new issue worktree.
- `list`: list all Herdr-managed worktrees.
- `review <issue|path|branch>`: inspect the matching worktree, branch, status, base commit, and Herdr workspace.
- `open <issue|path|branch>`: open an existing worktree in Herdr without creating another one.
- `remove <issue|path|branch>`: remove a worktree only when explicitly requested.
- `up|down|status|test <issue|path|branch>`: control that worktree's isolated Sail instance.

If the user names an issue without a mode, use `create` when no matching worktree exists; otherwise use `review` and report the existing one.

## Create

For `create`, derive these values from the issue:

- branch: `issue-<number>-<kebab-case-name>`
- path: `<repository-parent>/<repository-name>-issue-<number>`
- label: `Issue #<number> <Short Name>`
- base: `main`

Run the installed creation script, replacing only derived values:

```bash
test "${HERDR_ENV:-}" = 1 && \
  <skill-directory>/scripts/create.sh \
  <repository-root> <issue-number> <kebab-case-name> "<Short Name>"
```

The script creates the Herdr worktree, provisions its local dependencies, initializes a private CodeGraph index with `codegraph init -i <worktree-path>`, writes an isolated Compose project name and ephemeral host ports to its ignored `.env`, starts only `laravel.test` and its database/Redis dependencies, and installs the branch's locked dependencies. Read the Herdr JSON instead of guessing IDs. Do not focus the new workspace unless the user asks.

Once the worktree is ready, the script resolves that workspace's pane and starts the implementation agent inside it:

```bash
herdr pane run <pane-id> opencode2 run \
  --model openai/gpt-5.6-luna#high \
  "/implement <issue-number>"
```

Override the model or reasoning effort only when the user asks, through `WORKTREE_AGENT_MODEL` and `WORKTREE_AGENT_VARIANT`. The agent works the issue in its own tab; do not run `/implement` for that issue in this session as well.

Creation is complete only when all of these are true:

- Herdr reports `worktree_created`.
- The returned path and branch match the requested issue.
- The new checkout is clean.
- The source checkout's pre-existing status is unchanged.
- Its Compose project, containers, network, volumes, and bind mount belong only to the new path.
- Its `.codegraph/` index exists inside the new worktree and is not shared with another checkout.
- Its Sail services are running and ready for tests.
- Its pane runs `opencode2` on `/implement <issue-number>`.
- The final response includes path, branch, workspace ID, pane ID, base commit, and clean status.

## Sail

Resolve the worktree through `herdr worktree list`, then use the installed lifecycle script:

```bash
<skill-directory>/scripts/sail.sh <up|down|status|test> <worktree-path> [sail test command...]
```

`test` starts Sail when needed and stops it on exit only when it started the instance. Use `up` only while persistent services are required, and use `down` as soon as that work ends. A completed or abandoned worktree must not retain running services.

## List

For `list`, run:

```bash
test "${HERDR_ENV:-}" = 1 && herdr worktree list --cwd <repository-root>
```

Report every returned worktree with branch, path, and open Herdr workspace. Do not modify any worktree.

## Review

For `review`, list Herdr worktrees first, select the matching entry from the returned JSON, then inspect it with read-only Git commands:

```bash
git -C <path> status --short --branch
git -C <path> log -1 --oneline
```

Report the matching Herdr workspace, branch, path, current commit, dirty files, and whether it is safe to start work. Never repair or clean a worktree during review.

## Open

For `open`, use the matching path or branch returned by Herdr:

```bash
test "${HERDR_ENV:-}" = 1 && herdr worktree open \
  --cwd <repository-root> \
  --path <path> \
  --label "<label>" \
  --no-focus
```

Use `--branch` instead of `--path` only when that is the identifier the user supplied and it resolves uniquely. Report the workspace ID from Herdr.

## Remove

For `remove`, require an explicit user request. List first, resolve the exact workspace ID from Herdr, and only then run:

```bash
test "${HERDR_ENV:-}" = 1 && herdr worktree remove --workspace <workspace-id>
```

Stop its Sail instance before removal. Never remove `main`, a worktree belonging to another task, or a worktree with uncommitted changes without asking for confirmation.
