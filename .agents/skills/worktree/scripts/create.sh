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
    --base main \
    --path "$worktree_path" \
    --label "Issue #${issue_number} ${short_name}" \
    --no-focus

"$(dirname "$0")/sail.sh" prepare "$worktree_path" "$repository_root"
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

git -C "$worktree_path" status --short --branch
git -C "$worktree_path" log -1 --oneline
"$(dirname "$0")/sail.sh" status "$worktree_path"
