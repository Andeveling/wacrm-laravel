# Multi-Agent Worktree Launching

The worktree skill will not support selecting among multiple coding agents or
their distinct invocation adapters. Worktrees continue to launch the existing
OpenCode implementation flow through Herdr.

## Why this is out of scope

Supporting `opencode`, `claude`, `pi`, and `omp` would make the worktree skill
own and maintain CLI-specific launch behavior for tools with incompatible
interfaces. That is broader than the current worktree workflow needs and
would add an adapter matrix, validation behavior, dry-run handling, and
documentation without a committed use case for those agents.

A future, smaller adjustment to the existing OpenCode workflow may be scoped
separately. It must not implicitly reopen multi-agent selection or introduce
adapters for other CLIs.

## Prior requests

- #119 — "worktree: el agente debe ser un argumento, no opencode cableado"
