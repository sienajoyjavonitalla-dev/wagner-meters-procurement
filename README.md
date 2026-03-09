# Procurement Research Tool

Procurement pricing and alternate-part research platform, now centered on the Laravel + React application in `laravel/`.

## Repository Structure

- `laravel/`: primary web app (Laravel API + React dashboard)
- `scripts/`: legacy Python research scripts and validation helpers
- `dashboard/`: legacy Streamlit dashboard
- `phase5_1/`: validation artifacts (Python vs Laravel comparison)
- `phase5_2/`: cutover and sign-off artifacts

## Primary Workflow (Laravel + React)

1. Configure `laravel/.env` (DB + API keys).
2. Start app:
   - backend: `php artisan serve` (or Laragon web server)
   - frontend: `npm run dev`
3. Import data via UI (`/data-import`) or CLI:
   - `php artisan procurement:import-files <inventory.xlsx> <vendor_priority.csv> <item_spread.csv> --mpn-map=<mpn_map.csv>`
4. Build queue and run research:
   - `php artisan procurement:build-queue`
   - `php artisan procurement:run-research --sync`
5. Review in dashboard (`/dashboard`).

## Provider / AI Keys

Set in `laravel/.env` (see `laravel/config/procurement.php`):

- DigiKey: `DIGIKEY_CLIENT_ID`, `DIGIKEY_CLIENT_SECRET`
- Mouser: `MOUSER_API_KEY` (or `MOUSER_SEARCH_API_KEY`)
- Nexar: `NEXAR_CLIENT_ID`, `NEXAR_CLIENT_SECRET`
- Claude fallback: `ANTHROPIC_API_KEY`

## Phase 5 Artifacts

- Validation report: `phase5_1/PHASE_5_1_VALIDATION_REPORT.md`
- Cutover checklist: `phase5_2/CUTOVER_CHECKLIST.md`
- Stakeholder sign-off template: `phase5_2/STAKEHOLDER_SIGNOFF.md`

## Legacy Python Workflow (Reference)

Legacy scripts are still available for comparison and rollback planning:

- `scripts/research_loop.py`
- `dashboard/procurement_dashboard.py`
- runbook: `RESEARCH_AUTOMATION.md`

## Data Sensitivity

This workflow processes procurement spend and pricing data.

- Do not commit `.env` files
- Do not commit raw production spreadsheets
- Review `SECURITY.md` before sharing data or publishing changes
