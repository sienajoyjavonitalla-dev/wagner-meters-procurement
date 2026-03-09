# Procurement Research Automation

> Note: This document describes the legacy Python/Streamlit workflow.
> The primary production workflow is now Laravel + React (`laravel/`).
> For cutover operations, use `phase5_2/CUTOVER_CHECKLIST.md`.

This workflow automates two research tracks:

- Pricing research: benchmark current unit prices vs distributor/API findings.
- Alternate-part research: identify drop-in candidates to review.

## Script

Use:

`scripts/research_loop.py`

Inputs (defaults):

- `Inventory List with pricing.xlsx`
- `output/spreadsheet/vendor_priority_actions.csv`
- `output/spreadsheet/item_multisource_price_spread.csv`

Outputs:

- `output/research/research_queue.csv`
- `output/research/price_findings.csv`
- `output/research/prioritized_research_actions.csv`
- `output/research/fx_snapshot.csv`
- `output/research/mpn_map_template.csv`
- `output/research/part_master.csv`
- `output/research/mapping_review_queue.csv`

## Setup

Create or use the existing virtualenv and install packages:

```bash
cd /Users/ericwagner/Development/procurement
python3 -m venv .venv
./.venv/bin/pip install pandas openpyxl requests
```

Dashboard dependencies:

```bash
cd /Users/ericwagner/Development/procurement
./.venv/bin/pip install streamlit plotly
```

Create `.env` from template and fill keys as available:

```bash
cd /Users/ericwagner/Development/procurement
cp .env.example .env
```

## API Credentials (Optional but recommended)

The loop is API-first and only uses providers with configured keys.

`DigiKey`

- `DIGIKEY_CLIENT_ID`
- `DIGIKEY_CLIENT_SECRET`
- optional: `DIGIKEY_ACCOUNT_ID` (defaults `0` for catalog search)
- optional: `DIGIKEY_TOKEN_URL`, `DIGIKEY_PRODUCT_URL`

`Mouser`

- `MOUSER_API_KEY` (or `MOUSER_SEARCH_API_KEY`)
- optional: `MOUSER_PART_SEARCH_URL`

`Nexar / Octopart`

- `NEXAR_CLIENT_ID`
- `NEXAR_CLIENT_SECRET`
- optional: `NEXAR_TOKEN_URL`, `NEXAR_GRAPHQL_URL`

## Required Mapping for Reliable Matches

Many inventory IDs are internal part numbers, not distributor/manufacturer MPNs.
For reliable API matches, maintain:

- `output/research/mpn_map.csv`

Required columns:

- `Item ID`
- `mpn`

Notes:

- You can include multiple MPNs in one cell with `|` separators.
- To skip catalog API lookup for custom/internal items, set `mpn` to:
  - `NONCATALOG` (or `CUSTOM`, `INTERNAL_ONLY`)
- Optional: include `lookup_mode` column and set it to `non_catalog`.
- The script always writes `output/research/mpn_map_template.csv` with candidate hints.
- Fill the `mpn` column, save as `output/research/mpn_map.csv`, and rerun.

## Strict Mapping Behavior (Default)

The loop now runs in strict mapping mode by default:

- Only `mapping_status=mapped` items are researched against APIs.
- `non_catalog` items are marked `skipped_non_catalog`.
- Unmapped/uncertain items are marked `needs_mapping`.
- API results are accepted only when returned part numbers pass a strict match score against the mapped MPN.

This prevents internal item IDs or generic tokens from being treated as valid catalog matches.

## Run

API-only pass:

```bash
cd /Users/ericwagner/Development/procurement
./.venv/bin/python scripts/research_loop.py
```

Wrapper form (recommended for scheduling):

```bash
cd /Users/ericwagner/Development/procurement
./scripts/run_research_loop.sh
```

API + Codex fallback for unresolved items:

```bash
cd /Users/ericwagner/Development/procurement
./.venv/bin/python scripts/research_loop.py \
  --agent-fallback codex \
  --agent-batch-size 10 \
  --cwd /Users/ericwagner/Development/procurement
```

API + Claude fallback:

```bash
cd /Users/ericwagner/Development/procurement
./.venv/bin/python scripts/research_loop.py \
  --agent-fallback claude \
  --agent-batch-size 10
```

Wrapper with fallback:

```bash
cd /Users/ericwagner/Development/procurement
AGENT_FALLBACK=codex AGENT_BATCH_SIZE=10 ./scripts/run_research_loop.sh
```

## Dashboard

Launch the local dashboard:

```bash
cd /Users/ericwagner/Development/procurement
./scripts/run_dashboard.sh
```

Then open:

`http://localhost:8501`

Dashboard source:

- `dashboard/procurement_dashboard.py`

## How to Use the Outputs

- Start with `prioritized_research_actions.csv`.
- Filter `status == researched` and `estimated_savings > 0`.
- Treat `needs_research` rows as next batch for deeper agent/manual work.
- Keep human approval before supplier outreach or part substitutions.

## Laravel Cutover Commands (Phase 5.2)

Use these commands during cutover when switching to Laravel:

```bash
cd laravel
php artisan procurement:import-files <inventory.xlsx> <vendor_priority.csv> <item_spread.csv> --mpn-map=<mpn_map.csv>
php artisan procurement:build-queue --vendors=20 --per-vendor=50 --spread=100
php artisan procurement:run-research --limit=500 --sync
```

Then verify:

- `/dashboard`
- `/dashboard/run-controls`
- `/api/procurement/run-status?latest=1`
