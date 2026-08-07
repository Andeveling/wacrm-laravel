#!/usr/bin/env bash

set -Eeuo pipefail

usage() {
    echo "Usage: $0 <prepare|up|down|status|run|test> <worktree-path> [source-path|sail-args...]" >&2
    exit 2
}

command_name=${1:-}
worktree=${2:-}

[[ -n "$command_name" && -n "$worktree" ]] || usage
worktree=$(realpath "$worktree")

[[ -f "$worktree/compose.yaml" ]] || {
    echo "Not a WACRM checkout: $worktree" >&2
    exit 1
}

project_hash=$(printf '%s' "$worktree" | sha256sum | cut -c1-10)
project_slug=$(basename "$worktree" | tr '[:upper:]_' '[:lower:]-' | tr -cd 'a-z0-9-')
project_name="${project_slug:0:40}-${project_hash}"

write_environment() {
    local env_file="$worktree/.env"
    local temporary

    [[ -f "$env_file" ]] || cp "$worktree/.env.example" "$env_file"
    temporary=$(mktemp)
    awk '
        /^# BEGIN WORKTREE SAIL$/ { skipping = 1; next }
        /^# END WORKTREE SAIL$/ { skipping = 0; next }
        !skipping { print }
    ' "$env_file" > "$temporary"
    cat >> "$temporary" <<EOF

# BEGIN WORKTREE SAIL
COMPOSE_PROJECT_NAME=$project_name
APP_PORT=0
FORWARD_VITE_PORT=0
FORWARD_DB_PORT=0
FORWARD_REDIS_PORT=0
REVERB_SERVER_PORT=0
# END WORKTREE SAIL
EOF
    mv "$temporary" "$env_file"
}

prepare() {
    local source=${3:-}

    write_environment

    if [[ ! -x "$worktree/vendor/bin/sail" ]]; then
        [[ -n "$source" && -d "$source/vendor" ]] || {
            echo "A source checkout with vendor/ is required to bootstrap Sail." >&2
            exit 1
        }
        cp -a --reflink=auto "$source/vendor" "$worktree/vendor"
    fi

    if [[ ! -d "$worktree/node_modules" && -n "$source" && -d "$source/node_modules" ]]; then
        cp -a --reflink=auto "$source/node_modules" "$worktree/node_modules"
    fi
}

sail() {
    (cd "$worktree" && COMPOSE_PROJECT_NAME="$project_name" ./vendor/bin/sail "$@")
}

is_running() {
    [[ -x "$worktree/vendor/bin/sail" ]] && [[ -n "$(sail ps -q laravel.test 2>/dev/null)" ]]
}

case "$command_name" in
    prepare)
        prepare "$@"
        ;;
    up)
        write_environment
        if ! sail up -d laravel.test; then
            sail down --remove-orphans
            exit 1
        fi
        ;;
    down)
        [[ -x "$worktree/vendor/bin/sail" ]] && sail down --remove-orphans
        ;;
    status)
        echo "COMPOSE_PROJECT_NAME=$project_name"
        if [[ -x "$worktree/vendor/bin/sail" ]]; then
            sail ps
        else
            echo "Sail is not prepared."
        fi
        ;;
    run)
        shift 2
        (( $# > 0 )) || usage
        sail "$@"
        ;;
    test)
        shift 2
        started_here=false
        if ! is_running; then
            started_here=true
            cleanup() {
                if [[ "$started_here" == true ]]; then
                    sail down --remove-orphans
                fi
            }
            trap cleanup EXIT INT TERM
            sail up -d laravel.test
        fi
        if (( $# == 0 )); then
            sail artisan test --compact
        else
            sail "$@"
        fi
        ;;
    *)
        usage
        ;;
esac
