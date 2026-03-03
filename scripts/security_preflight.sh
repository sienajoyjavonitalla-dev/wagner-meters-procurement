#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "== Procurement Security Preflight =="
echo "Workspace: $ROOT_DIR"
echo

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "-- Git ignore check"
  git check-ignore -v .env .venv/ "Inventory List with pricing.xlsx" output/ || true
  echo
else
  echo "-- Git ignore check"
  echo "Not a git repo yet. After 'git init', run:"
  echo "  git check-ignore -v .env .venv/ \"Inventory List with pricing.xlsx\" output/"
  echo
fi

echo "-- Secret scan (regex fallback)"
rg -n --hidden -g '!.env' -g '!.venv/**' \
  -e 'DIGIKEY_CLIENT_SECRET|MOUSER_API_KEY|NEXAR_CLIENT_SECRET|OPENAI_API_KEY|ANTHROPIC_API_KEY|-----BEGIN' \
  . || true
echo

echo "-- If gitleaks is installed, run:"
echo "  gitleaks dir --source . --redact"
echo

echo "-- Suggested initial safe commit scope"
cat <<'EOF'
.gitignore
.env.example
README.md
RESEARCH_AUTOMATION.md
SECURITY.md
scripts/research_loop.py
scripts/run_research_loop.sh
scripts/run_dashboard.sh
scripts/security_preflight.sh
dashboard/procurement_dashboard.py
.streamlit/config.toml
EOF
