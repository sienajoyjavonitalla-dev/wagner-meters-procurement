#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -f ".env" ]]; then
  # shellcheck disable=SC1091
  source ".env"
fi

PYTHON_BIN="${PYTHON_BIN:-$ROOT_DIR/.venv/bin/python}"
AGENT_FALLBACK="${AGENT_FALLBACK:-}"
AGENT_BATCH_SIZE="${AGENT_BATCH_SIZE:-0}"

ARGS=(
  "scripts/research_loop.py"
  "--inventory" "Inventory List with pricing.xlsx"
  "--vendor-priority" "output/spreadsheet/vendor_priority_actions.csv"
  "--item-spread" "output/spreadsheet/item_multisource_price_spread.csv"
  "--outdir" "output/research"
  "--cwd" "$ROOT_DIR"
)

if [[ -n "$AGENT_FALLBACK" ]]; then
  ARGS+=("--agent-fallback" "$AGENT_FALLBACK" "--agent-batch-size" "$AGENT_BATCH_SIZE")
fi

"$PYTHON_BIN" "${ARGS[@]}"

