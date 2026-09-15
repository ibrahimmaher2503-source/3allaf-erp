#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"

if [[ -z "$ROOT" ]]; then
    echo "Error: run this command inside a Git repository." >&2
    exit 1
fi

cd "$ROOT"

EXPECTED_PROJECT="toy-joy-phase-1-documentation"
PROJECT_NAME="${PROJECT_NAME:-$(basename "$ROOT")}"
MODEL="${AGY_MODEL:-gemini-3.1-pro}"
EFFORT="${AGY_EFFORT:-high}"
TASK_FILE="${1:-.ai/CURRENT_TASK.md}"

if [[ "$PROJECT_NAME" != "$EXPECTED_PROJECT" ]]; then
    echo "Error: project identity mismatch: expected $EXPECTED_PROJECT, got $PROJECT_NAME." >&2
    exit 1
fi

if [[ ! -f "$TASK_FILE" ]]; then
    echo "Error: task file not found: $TASK_FILE" >&2
    exit 1
fi

if ! command -v agy >/dev/null 2>&1; then
    echo "Error: agy is not installed or not available in PATH." >&2
    exit 1
fi

mkdir -p .ai/logs

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
LOG_FILE=".ai/logs/agy-${TIMESTAMP}.json"

PROMPT=$'Repository identity (mandatory): project='"$PROJECT_NAME"'; git_root='"$ROOT"'. Before analysis, run pwd and git rev-parse --show-toplevel and include both exact outputs in the report. Never inspect or modify a different repository.\n\n'
PROMPT+="$(cat "$TASK_FILE")"

agy -p "$PROMPT" \
    --model "$MODEL" \
    --effort "$EFFORT" \
    --add-dir "$ROOT" \
    --output-format json \
    --dangerously-skip-permissions \
    > "$LOG_FILE"

echo "Gemini finished."
echo "Log: $LOG_FILE"
echo
echo "Changed files:"
git status --short
echo
echo "Diff summary:"
git diff --stat
