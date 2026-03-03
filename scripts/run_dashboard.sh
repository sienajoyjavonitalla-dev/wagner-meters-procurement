#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PYTHON_BIN="${PYTHON_BIN:-$ROOT_DIR/.venv/bin/python}"
PORT="${PORT:-8501}"
ADDRESS="${ADDRESS:-127.0.0.1}"

exec "$PYTHON_BIN" -m streamlit run \
  dashboard/procurement_dashboard.py \
  --server.address "$ADDRESS" \
  --server.port "$PORT" \
  --server.headless true \
  --server.enableCORS true \
  --server.enableXsrfProtection true
