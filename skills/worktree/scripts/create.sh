#!/usr/bin/env bash

set -Eeuo pipefail

[[ "${HERDR_ENV:-}" == 1 ]] || {
    echo "This command must run inside Herdr." >&2
    exit 1
}

repository_root=${1:-}
issue_number=${2:-}
issue_slug=${3:-}
short_name=${4:-}

if [[ -z "$repository_root" || -z "$issue_number" || -z "$issue_slug" || -z "$short_name" ]]; then
    echo "Usage: $0 <repository-root> <issue-number> <issue-slug> <short-name>" >&2
    exit 2
fi

repository_root=$(realpath "$repository_root")
repository_name=$(basename "$repository_root")
worktree_path="$(dirname "$repository_root")/${repository_name}-issue-${issue_number}"
branch="issue-${issue_number}-${issue_slug}"

herdr worktree create \
    --cwd "$repository_root" \
    --branch "$branch" \
    --base develop \
    --path "$worktree_path" \
    --label "Issue #${issue_number} ${short_name}" \
    --no-focus

"$(dirname "$0")/sail.sh" prepare "$worktree_path" "$repository_root"
codegraph init -i "$worktree_path"
keep_running=false
cleanup() {
    if [[ "$keep_running" == false ]]; then
        "$(dirname "$0")/sail.sh" down "$worktree_path"
    fi
}
trap cleanup EXIT INT TERM

"$(dirname "$0")/sail.sh" up "$worktree_path"
"$(dirname "$0")/sail.sh" run "$worktree_path" composer install --no-interaction
"$(dirname "$0")/sail.sh" run "$worktree_path" pnpm install --frozen-lockfile
"$(dirname "$0")/sail.sh" run "$worktree_path" artisan key:generate --force
keep_running=true

workspace_id=$(herdr worktree list --cwd "$repository_root" |
    jq -er --arg path "$worktree_path" '.result.worktrees[] | select(.path == $path) | .open_workspace_id')
pane_id=$(herdr pane list --workspace "$workspace_id" | jq -er '.result.panes[0].pane_id')

herdr pane run "$pane_id" opencode2

git -C "$worktree_path" status --short --branch
git -C "$worktree_path" log -1 --oneline
"$(dirname "$0")/sail.sh" status "$worktree_path"
echo "opencode2 started in ${pane_id} (workspace ${workspace_id})"
