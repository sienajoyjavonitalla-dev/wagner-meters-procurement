# Wagner Meters Procurement (Laravel + React)

Primary application for the procurement research workflow.

## Local Setup

1. Create/update database and migrate:

```bash
cd laravel
php artisan migrate
```

2. Install frontend dependencies and run Vite:

```bash
npm install
npm run dev
```

3. Start Laravel (if not using Laragon virtual host):

```bash
php artisan serve
```

## Authentication

- Register/login through `/register` and `/login`
- Protected views require auth:
  - `/dashboard`
  - `/data-import`
  - `/profile`

## Data Import

Use UI (`/data-import`) or CLI:

```bash
php artisan procurement:import-files path/to/inventory.xlsx path/to/vendor_priority.csv path/to/item_spread.csv --mpn-map=path/to/mpn_map.csv
```

## Research Pipeline Commands

```bash
php artisan procurement:build-queue --vendors=20 --per-vendor=50 --spread=100
php artisan procurement:run-research --limit=500 --sync
```

Optional flags:

- `--build` (on `procurement:run-research`) to build queue first
- `--no-claude` to disable Claude fallback
- `--batch=<uuid>` to run a specific batch

## Environment Variables (Provider Keys)

Configured via `config/procurement.php`:

- DigiKey: `DIGIKEY_CLIENT_ID`, `DIGIKEY_CLIENT_SECRET`
- Mouser: `MOUSER_API_KEY` (or `MOUSER_SEARCH_API_KEY`)
- Nexar: `NEXAR_CLIENT_ID`, `NEXAR_CLIENT_SECRET`
- Claude: `ANTHROPIC_API_KEY`

Research behavior:

- `PROCUREMENT_STRICT_MAPPING` (default true)
- `PROCUREMENT_MIN_MATCH_SCORE` (default 0.9)
- `PROCUREMENT_CLAUDE_BATCH_SIZE` (default 50)

## Phase 5 Operations

- Validation report: `../phase5_1/PHASE_5_1_VALIDATION_REPORT.md`
- Cutover checklist: `../phase5_2/CUTOVER_CHECKLIST.md`
- Stakeholder sign-off: `../phase5_2/STAKEHOLDER_SIGNOFF.md`
