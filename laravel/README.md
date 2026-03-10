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

**Important:** Each full import **replaces** the previous one. The job deletes the prior import’s suppliers, items, purchases, mappings, vendor priorities, and item spreads. Research tasks (and Digi-Key/Mouser price findings) tied to those items are removed by cascade. The new import becomes the “current” dataset.

### Testing import file changes

1. **Back up the database** if you want to keep the current state (e.g. `mysqldump` or export).
2. **Use small/sample files** to try column changes or new MPN maps without affecting production data, or run on a copy of the app/DB.
3. **Ensure the queue worker is running** so `ProcessImportJob` runs after upload:
   ```bash
   php artisan queue:work
   ```
4. Upload via **Data Import** and check the import row in the table (status, row counts). Fix any validation errors reported by the form.

### Applying import changes with Digi-Key (and other providers)

1. **Upload** the four files on **Data Import** (inventory, vendor priority, item spread, optional MPN map). Wait until the import shows **completed** (refresh the page; the queue job runs in the background).
2. **Rebuild the research queue** from the new import and run research:
   - **UI:** Go to **Run Controls**. Leave **Build queue** checked and click **Start run**. This builds the queue from the latest completed import and runs Digi-Key/Mouser/Nexar (and Claude fallback) for the new items.
   - **CLI:**
     ```bash
     php artisan procurement:build-queue --vendors=20 --per-vendor=50 --spread=100
     php artisan procurement:run-research --limit=500
     ```
     Or build and run in one step: `php artisan procurement:run-research --build --limit=500`
3. **Review** results on the dashboard, vendor progress, and mapping review as needed.

There is no “merge” with old Digi-Key data: the new import is the new source of truth; queue and research are rebuilt from it.

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
