# Procurement Research Automation

Internal tooling for procurement pricing research, alternate-part discovery, and prioritized savings actions.

This project is designed to run locally with API credentials loaded from `.env`.

## What This Repository Contains

- `scripts/research_loop.py`: core research pipeline
- `scripts/run_research_loop.sh`: wrapper to run the pipeline
- `dashboard/procurement_dashboard.py`: local Streamlit dashboard
- `scripts/run_dashboard.sh`: wrapper to run dashboard
- `RESEARCH_AUTOMATION.md`: operational runbook

## Quick Start

1. Create virtualenv and install dependencies:

```bash
python3 -m venv .venv
./.venv/bin/pip install pandas openpyxl requests streamlit plotly
```

2. Configure credentials:

```bash
cp .env.example .env
# then fill DIGIKEY/MOUSER/NEXAR keys in .env
```

3. Run research:

```bash
./scripts/run_research_loop.sh
```

4. Launch dashboard:

```bash
./scripts/run_dashboard.sh
# open http://127.0.0.1:8501
```

## Pre-Push Security Check

Run this before first push (and before later pushes):

```bash
./scripts/security_preflight.sh
```

## Data Sensitivity

This workflow processes procurement spend and pricing data.

- Do not commit `.env`
- Do not commit raw inventory spreadsheets or generated `output/` data
- Use `SECURITY.md` before first push
